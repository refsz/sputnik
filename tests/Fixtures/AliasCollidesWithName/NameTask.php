<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\AliasCollidesWithName;

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'real:name', description: 'The real task')]
final class NameTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success();
    }
}
