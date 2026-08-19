<?php

declare(strict_types=1);

namespace Sputnik\Tests\Support\Doubles;

use PHPUnit\Framework\Assert;
use Sputnik\Executor\ExecutionResult;
use Sputnik\Executor\ExecutorInterface;

final class FakeShellExecutor implements ExecutorInterface
{
    /**
     * @var array<string, ExecutionResult>
     */
    private array $responses = [];

    /**
     * @var list<array{command: list<string>|string, options: array}>
     */
    private array $executedCommands = [];

    public function willReturn(string $command, ExecutionResult $result): void
    {
        $this->responses[$command] = $result;
    }

    public function willSucceed(string $command, string $output = ''): void
    {
        $this->responses[$command] = new ExecutionResult(
            exitCode: 0,
            output: $output,
            errorOutput: '',
            duration: 0.1,
            command: $command,
        );
    }

    public function willFail(string $command, string $error = 'Command failed', int $exitCode = 1): void
    {
        $this->responses[$command] = new ExecutionResult(
            exitCode: $exitCode,
            output: '',
            errorOutput: $error,
            duration: 0.1,
            command: $command,
        );
    }

    /**
     * @param list<string>|string $command
     */
    public function execute(array|string $command, array $options = []): ExecutionResult
    {
        if ($command === []) {
            throw new \InvalidArgumentException('Cannot execute an empty command list');
        }

        $this->executedCommands[] = ['command' => $command, 'options' => $options];

        $key = \is_array($command) ? implode(' ', $command) : $command;

        if (isset($this->responses[$key])) {
            return $this->responses[$key];
        }

        // Default: succeed with empty output
        return new ExecutionResult(
            exitCode: 0,
            output: '',
            errorOutput: '',
            duration: 0.1,
            command: $key,
        );
    }

    public function assertExecuted(string $command): void
    {
        foreach ($this->executedCommands as $executed) {
            if ($executed['command'] === $command) {
                return;
            }
        }

        Assert::fail("Command was not executed: {$command}");
    }

    public function assertNotExecuted(string $command): void
    {
        foreach ($this->executedCommands as $executed) {
            if ($executed['command'] === $command) {
                Assert::fail("Command was executed but should not have been: {$command}");
            }
        }
    }

    /**
     * @return list<array{command: list<string>|string, options: array}>
     */
    public function getExecutedCommands(): array
    {
        return $this->executedCommands;
    }

    public function reset(): void
    {
        $this->responses = [];
        $this->executedCommands = [];
    }
}
