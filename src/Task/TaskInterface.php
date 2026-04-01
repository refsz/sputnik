<?php

declare(strict_types=1);

namespace Sputnik\Task;

interface TaskInterface
{
    /**
     * Execute the task.
     *
     * @param TaskContext $context Runtime context with variables, options, and services
     *
     * @return TaskResult The result of the task execution
     */
    public function __invoke(TaskContext $context): TaskResult;
}
