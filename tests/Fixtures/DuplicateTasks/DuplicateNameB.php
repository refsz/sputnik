<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\DuplicateTasks;

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'duplicate:name', description: 'Second task with same name')]
final class DuplicateNameB implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success();
    }
}
