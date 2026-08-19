<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\CollidesWithStructural;

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'list', description: 'Collides with a structural command')]
final class ListTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success();
    }
}
