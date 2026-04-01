<?php

declare(strict_types=1);

namespace Sputnik\Task;

use Psr\Log\LoggerInterface;
use Sputnik\Console\SputnikOutput;
use Sputnik\Executor\ExecutionResult;
use Sputnik\Executor\ExecutorInterface;
use Sputnik\Variable\VariableResolverInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskContext
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private readonly VariableResolverInterface $variables,
        private readonly array $options,
        private readonly array $arguments,
        private readonly string $contextName,
        private readonly string $workingDir,
        private readonly LoggerInterface $logger,
        private readonly ExecutorInterface $shellExecutor,
        private readonly TaskRunnerInterface $taskRunner,
        private readonly ?OutputInterface $output = null,
        private readonly ?SputnikOutput $sputnikOutput = null,
    ) {
    }

    /**
     * Get a resolved variable value.
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->variables->resolve($name, $default);
    }

    /**
     * Get an option value.
     */
    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Get an argument value.
     */
    public function argument(string $name, mixed $default = null): mixed
    {
        return $this->arguments[$name] ?? $default;
    }

    /**
     * Execute a shell command with variable interpolation.
     *
     * Variables in the command using {{ VAR }} syntax will be replaced with their values.
     * Example: $context->shell('echo {{ APP_ENV }}')
     *
     * @param string                                                          $command The command to execute (supports {{ VAR }} syntax)
     * @param array{env?: array<string, string>, tty?: bool, timeout?: float} $options
     */
    public function shell(string $command, array $options = []): ExecutionResult
    {
        $interpolated = $this->interpolateCommand($command);

        return $this->shellExecutor->execute($interpolated, [
            'cwd' => $this->workingDir,
            'env' => $options['env'] ?? [],
            'tty' => $options['tty'] ?? false,
            'timeout' => $options['timeout'] ?? null,
        ]);
    }

    /**
     * Execute a shell command without variable interpolation (raw).
     *
     * @param string                                                          $command The command to execute as-is
     * @param array{env?: array<string, string>, tty?: bool, timeout?: float} $options
     */
    public function shellRaw(string $command, array $options = []): ExecutionResult
    {
        return $this->shellExecutor->execute($command, [
            'cwd' => $this->workingDir,
            'env' => $options['env'] ?? [],
            'tty' => $options['tty'] ?? false,
            'timeout' => $options['timeout'] ?? null,
        ]);
    }

    /**
     * Run another task programmatically with custom arguments/options.
     *
     * @param string               $taskName  The task name (e.g., 'db:migrate')
     * @param array<string, mixed> $arguments Positional arguments
     * @param array<string, mixed> $options   Named options
     */
    public function runTask(string $taskName, array $arguments = [], array $options = []): TaskResult
    {
        $savedSteps = $this->sputnikOutput?->saveSteps();
        $this->sputnikOutput?->resetSteps();

        $result = $this->taskRunner->run($taskName, $arguments, $options, $this->output, [], $this->sputnikOutput);

        if ($savedSteps !== null) {
            $this->sputnikOutput?->restoreSteps($savedSteps);
        }

        return $result;
    }

    /**
     * Write directly to console output.
     */
    public function writeln(string $message): void
    {
        if ($this->output instanceof OutputInterface) {
            $this->output->writeln($message);
        }
    }

    /**
     * Write directly to console output without newline.
     */
    public function write(string $message): void
    {
        if ($this->output instanceof OutputInterface) {
            $this->output->write($message);
        }
    }

    /**
     * Write a success/normal message (always shown, green).
     */
    public function success(string $message): void
    {
        if ($this->output instanceof OutputInterface) {
            $this->output->writeln('<info>' . $message . '</info>');
        }
    }

    /**
     * Log a message.
     *
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * Log an error message.
     *
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * Get the current context name.
     */
    public function getContextName(): string
    {
        return $this->contextName;
    }

    /**
     * Get the working directory.
     */
    public function getWorkingDir(): string
    {
        return $this->workingDir;
    }

    /**
     * Get all options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get all arguments.
     *
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Interpolate variables in a command string.
     *
     * Replaces {{ VAR }} with variable values.
     * Supports {{ VAR | "default" }} syntax for defaults.
     */
    private function interpolateCommand(string $command): string
    {
        // Pattern matches {{ VAR }} or {{ VAR | "default" }} or {{ VAR | 'default' }}
        $pattern = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*(?:\|\s*["\']([^"\']*)["\'])?\s*\}\}/';

        return preg_replace_callback($pattern, function (array $matches): string {
            $name = $matches[1];
            $default = $matches[2] ?? '';

            $value = $this->variables->resolve($name, $default);

            // Convert to string for shell, escaping to prevent command injection
            if (\is_bool($value)) {
                return escapeshellarg($value ? 'true' : 'false');
            }

            if (\is_array($value)) {
                $encoded = json_encode($value);

                return escapeshellarg($encoded !== false ? $encoded : '[]');
            }

            return escapeshellarg((string) $value);
        }, $command) ?? $command;
    }
}
