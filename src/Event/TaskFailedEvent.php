<?php

declare(strict_types=1);

namespace Sputnik\Event;

use Sputnik\Task\TaskMetadata;
use Symfony\Contracts\EventDispatcher\Event;

final class TaskFailedEvent extends Event
{
    public function __construct(
        public readonly TaskMetadata $task,
        public readonly \Throwable $exception,
    ) {
    }

    public function getErrorMessage(): string
    {
        return $this->exception->getMessage();
    }
}
