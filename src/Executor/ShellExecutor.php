<?php

declare(strict_types=1);

namespace Sputnik\Executor;

use Sputnik\Console\OutputChannel;
use Sputnik\Console\SputnikOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class ShellExecutor implements ExecutorInterface
{
    private const DEFAULT_TIMEOUT = 300.0; // 5 minutes

    private ?Process $activeProcess = null;

    public function __construct(
        private readonly OutputChannel $channel = new OutputChannel(),
        private readonly float $defaultTimeout = self::DEFAULT_TIMEOUT,
    ) {
    }

    public function stop(): void
    {
        $this->activeProcess?->stop(0);
        $this->activeProcess = null;
    }

    /**
     * @param list<string>|string                                                                              $command
     * @param array{cwd?: string, env?: array<string, string>, timeout?: float|null, tty?: bool, quiet?: bool} $options
     */
    public function execute(array|string $command, array $options = []): ExecutionResult
    {
        $display = $this->display($command);

        $cwdFallback = getcwd();
        $cwd = $options['cwd'] ?? ($cwdFallback !== false ? $cwdFallback : null);
        $tty = $options['tty'] ?? false;
        $timeout = $tty ? 0 : ($options['timeout'] ?? $this->defaultTimeout);

        // The command line and its outcome still show - what a task asked to
        // keep quiet is the output, because it wants it as a value rather than
        // on the terminal.
        $quiet = $options['quiet'] ?? false;

        // The task has the last word: its own value for a variable wins.
        $env = ($options['env'] ?? []) + $this->colourEnvironment($quiet, $tty);

        $this->channel->sputnikOutput()?->command($display);

        $startTime = microtime(true);

        $process = $this->createProcess($command, $cwd, $env, $timeout);

        if ($tty && Process::isTtySupported()) {
            $process->setTty(true);
        }

        $output = '';
        $errorOutput = '';

        $this->activeProcess = $process;
        $process->run(function (string $type, string $buffer) use (&$output, &$errorOutput, $quiet): void {
            if ($type === Process::OUT) {
                $output .= $buffer;

                if (!$quiet) {
                    $this->streamOutput($buffer, false);
                }
            } else {
                $errorOutput .= $buffer;

                if (!$quiet) {
                    $this->streamOutput($buffer, true);
                }
            }
        });
        $this->activeProcess = null;

        $duration = microtime(true) - $startTime;
        $exitCode = $process->getExitCode() ?? 1;

        $this->channel->sputnikOutput()?->commandDone($duration, $exitCode);

        return new ExecutionResult(
            exitCode: $exitCode,
            output: $output,
            errorOutput: $errorOutput,
            duration: $duration,
            command: $display,
        );
    }

    /**
     * @param list<string>|string   $command
     * @param array<string, string> $env
     */
    private function createProcess(array|string $command, ?string $cwd, array $env, ?float $timeout): Process
    {
        if (!\is_array($command)) {
            return Process::fromShellCommandline($command, $cwd, $env, null, $timeout);
        }

        if ($command === []) {
            throw new \InvalidArgumentException('Cannot execute an empty command list');
        }

        return new Process($command, $cwd, $env, null, $timeout);
    }

    /**
     * Tell a command that colour is welcome, when it is.
     *
     * A tool decides on colour by asking whether its output is a terminal, and
     * through Sputnik it never is: everything runs through pipes because the
     * output has to be captured - to mask secrets in it, to indent it, to put it
     * on the result. So `composer install` through a task came out monochrome
     * where the same command in a shell is coloured.
     *
     * FORCE_COLOR says what the pipe cannot. Symfony Console reads it, and so do
     * the Node tools, which covers composer, drush, npm and everything built on
     * either. It follows Sputnik's own output, so a redirected run or --no-ansi
     * still writes a clean log, and NO_COLOR needs no handling of its own -
     * it already turns our own decoration off.
     *
     * Not for a quiet command: there the output is a value, and escape codes in
     * a value corrupt whatever reads it. Not under a tty either, where the
     * command has the real terminal and decides for itself.
     *
     * @return array<string, string>
     */
    private function colourEnvironment(bool $quiet, bool $tty): array
    {
        if ($quiet || $tty) {
            return [];
        }

        if ($this->channel->output()?->isDecorated() !== true) {
            return [];
        }

        // Someone who set it themselves has an opinion; leave it alone.
        if (getenv('FORCE_COLOR') !== false) {
            return [];
        }

        return ['FORCE_COLOR' => '1'];
    }

    /**
     * A command as shown to the user and recorded on the result. Joining an
     * argv list is for display only and makes no quoting promise.
     *
     * @param list<string>|string $command
     */
    private function display(array|string $command): string
    {
        return \is_array($command) ? implode(' ', $command) : $command;
    }

    private function streamOutput(string $buffer, bool $isError): void
    {
        $target = $this->channel->output();

        if (!$target instanceof OutputInterface) {
            return;
        }

        if ($this->channel->sputnikOutput() instanceof SputnikOutput) {
            $indented = '  ' . str_replace("\n", "\n  ", rtrim($buffer, "\n")) . "\n";
            $target->write($indented, false, OutputInterface::OUTPUT_RAW);
        } elseif ($isError) {
            $target->write('<error>' . $buffer . '</error>');
        } else {
            $target->write($buffer);
        }
    }
}
