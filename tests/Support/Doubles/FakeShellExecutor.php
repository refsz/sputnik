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
     * @var list<array{command: string, options: array}>
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

    public function execute(string $command, array $options = []): ExecutionResult
    {
        $this->executedCommands[] = ['command' => $command, 'options' => $options];

        if (isset($this->responses[$command])) {
            return $this->responses[$command];
        }

        // Default: succeed with empty output
        return new ExecutionResult(
            exitCode: 0,
            output: '',
            errorOutput: '',
            duration: 0.1,
            command: $command,
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
     * @return list<array{command: string, options: array}>
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
