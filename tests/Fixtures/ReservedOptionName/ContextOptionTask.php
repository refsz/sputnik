<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\ReservedOptionName;

use Sputnik\Attribute\Option;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'bad:task', description: 'Has reserved option name')]
final class ContextOptionTask implements TaskInterface
{
    #[Option(name: 'context', description: 'This collides')]
    private string $context;

    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success();
    }
}
