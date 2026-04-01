<?php

declare(strict_types=1);

namespace Sputnik\Task;

use Sputnik\Exception\SputnikException;

final class TaskNotFoundException extends SputnikException
{
    public static function forTask(string $taskName): self
    {
        return new self(\sprintf('Task not found: %s', $taskName));
    }
}
