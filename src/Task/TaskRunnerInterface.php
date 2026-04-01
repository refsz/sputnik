<?php

declare(strict_types=1);

namespace Sputnik\Task;

use Sputnik\Console\SputnikOutput;
use Symfony\Component\Console\Output\OutputInterface;

interface TaskRunnerInterface
{
    /**
     * Run a task by name with optional arguments and options.
     *
     * @param string                   $taskName         The task name (e.g., 'db:migrate')
     * @param array<int|string, mixed> $arguments        Positional arguments (int keys for positional, string keys for named)
     * @param array<string, mixed>     $options          Named options
     * @param OutputInterface|null     $output           Console output for logging (optional)
     * @param array<string, mixed>     $runtimeVariables Runtime variable overrides (-D NAME=value)
     */
    public function run(
        string $taskName,
        array $arguments = [],
        array $options = [],
        ?OutputInterface $output = null,
        array $runtimeVariables = [],
        ?SputnikOutput $sputnikOutput = null,
    ): TaskResult;

    public function getContextName(): string;
}
