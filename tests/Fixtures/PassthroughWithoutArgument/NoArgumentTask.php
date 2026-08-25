<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\PassthroughWithoutArgument;

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'wrap:no-argument', description: 'Nothing to forward into', passthrough: true)]
final class NoArgumentTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success();
    }
}
