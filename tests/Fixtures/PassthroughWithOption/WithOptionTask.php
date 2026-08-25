<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\PassthroughWithOption;

use Sputnik\Attribute\Argument;
use Sputnik\Attribute\Option;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'wrap:with-option', description: 'Declares an option it can never receive', passthrough: true)]
final class WithOptionTask implements TaskInterface
{
    #[Option(name: 'force', description: 'Unreachable behind the separator')]
    private bool $force = false;

    #[Argument(name: 'args', description: 'Forwarded', isArray: true)]
    private array $args = [];

    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success();
    }
}
