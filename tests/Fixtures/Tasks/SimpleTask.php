<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\Tasks;

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(
    name: 'test:simple',
    description: 'A simple test task',
    aliases: ['simple'],
)]
final class SimpleTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success('Simple task completed');
    }
}
