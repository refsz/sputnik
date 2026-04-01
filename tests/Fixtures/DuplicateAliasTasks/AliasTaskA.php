<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\DuplicateAliasTasks;

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'alias:first', description: 'First', aliases: ['shared'])]
final class AliasTaskA implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success();
    }
}
