<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\CollidesWithStructural;

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'deploy', description: 'Has nothing to do with the collision')]
final class UnrelatedTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success();
    }
}
