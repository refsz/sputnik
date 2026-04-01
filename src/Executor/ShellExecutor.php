<?php

declare(strict_types=1);

namespace Sputnik\Executor;

use Sputnik\Console\SputnikOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class ShellExecutor implements ExecutorInterface
{
    private const DEFAULT_TIMEOUT = 300.0; // 5 minutes

    private ?Process $activeProcess = null;

    public function __construct(
        private readonly ?OutputInterface $output = null,
        private readonly float $defaultTimeout = self::DEFAULT_TIMEOUT,
        private readonly ?SputnikOutput $sputnikOutput = null,
    ) {
    }

    public function stop(): void
    {
        $this->activeProcess?->stop(0);
        $this->activeProcess = null;
    }

    /**
     * @param array{cwd?: string, env?: array<string, string>, timeout?: float, tty?: bool} $options
     *
     * @phpstan-ignore method.childParameterType
     */
    public function execute(string $command, array $options = []): ExecutionResult
    {
        $cwdFallback = getcwd();
        $cwd = $options['cwd'] ?? ($cwdFallback !== false ? $cwdFallback : null);
        $env = $options['env'] ?? [];
        $tty = $options['tty'] ?? false;
        $timeout = $tty ? 0 : ($options['timeout'] ?? $this->defaultTimeout);

        $this->sputnikOutput?->command($command);

        $startTime = microtime(true);

        $process = Process::fromShellCommandline($command, $cwd, $env, null, $timeout);

        if ($tty && Process::isTtySupported()) {
            $process->setTty(true);
        }

        $output = '';
        $errorOutput = '';

        $this->activeProcess = $process;
        $process->run(function (string $type, string $buffer) use (&$output, &$errorOutput): void {
            if ($type === Process::OUT) {
                $output .= $buffer;
                $this->streamOutput($buffer, false);
            } else {
                $errorOutput .= $buffer;
                $this->streamOutput($buffer, true);
            }
        });
        $this->activeProcess = null;

        $duration = microtime(true) - $startTime;
        $exitCode = $process->getExitCode() ?? 1;

        $this->sputnikOutput?->commandDone($duration, $exitCode);

        return new ExecutionResult(
            exitCode: $exitCode,
            output: $output,
            errorOutput: $errorOutput,
            duration: $duration,
            command: $command,
        );
    }

    /**
     * Execute a command without streaming output.
     *
     * @param array{cwd?: string, env?: array<string, string>, timeout?: float} $options
     */
    public function executeQuiet(string $command, array $options = []): ExecutionResult
    {
        $cwdFallback = getcwd();
        $cwd = $options['cwd'] ?? ($cwdFallback !== false ? $cwdFallback : null);
        $env = $options['env'] ?? [];
        $timeout = $options['timeout'] ?? $this->defaultTimeout;

        $startTime = microtime(true);

        $process = Process::fromShellCommandline($command, $cwd, $env, null, $timeout);
        $process->run();

        $duration = microtime(true) - $startTime;

        return new ExecutionResult(
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
            duration: $duration,
            command: $command,
        );
    }

    private function streamOutput(string $buffer, bool $isError): void
    {
        $target = $this->sputnikOutput?->getOutput() ?? $this->output;

        if (!$target instanceof OutputInterface) {
            return;
        }

        if ($this->sputnikOutput instanceof SputnikOutput) {
            $indented = '  ' . str_replace("\n", "\n  ", rtrim($buffer, "\n")) . "\n";
            $target->write($indented, false, OutputInterface::OUTPUT_RAW);
        } elseif ($isError) {
            $target->write('<error>' . $buffer . '</error>');
        } else {
            $target->write($buffer);
        }
    }
}
