<?php

declare(strict_types=1);

namespace Sputnik\Executor;

interface ExecutorInterface
{
    /**
     * Execute a command. The argument type selects the mode: a list is passed
     * to the process directly, with no shell involved, so argument boundaries
     * are preserved; a string is run through a shell, for pipes and redirects.
     *
     * @param list<string>|string                                                                $command Program and arguments, or a shell command line
     * @param array{cwd?: string, env?: array<string, string>, timeout?: float|null, tty?: bool} $options
     *
     * @return ExecutionResult Result with output, error output, and exit code
     */
    public function execute(array|string $command, array $options = []): ExecutionResult;
}
