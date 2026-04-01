<?php

declare(strict_types=1);

namespace Sputnik\Executor;

interface ExecutorInterface
{
    /**
     * Execute a command.
     *
     * @param string               $command Command to execute
     * @param array<string, mixed> $options Execution options (cwd, env, timeout, etc.)
     *
     * @return ExecutionResult Result with output, error output, and exit code
     */
    public function execute(string $command, array $options = []): ExecutionResult;
}
