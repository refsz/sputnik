<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\ReservedOptionShortcut;

use Sputnik\Attribute\Option;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'bad:shortcut', description: 'Has reserved shortcut')]
final class ShortcutDTask implements TaskInterface
{
    #[Option(name: 'debug', shortcut: 'D', description: 'Collides with -D')]
    private bool $debug;

    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success();
    }
}
