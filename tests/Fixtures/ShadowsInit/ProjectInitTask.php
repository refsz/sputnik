<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\ShadowsInit;

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'init', description: 'The project has its own init')]
final class ProjectInitTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success();
    }
}
