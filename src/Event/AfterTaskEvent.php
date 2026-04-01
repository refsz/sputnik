<?php

declare(strict_types=1);

namespace Sputnik\Event;

use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskResult;
use Symfony\Contracts\EventDispatcher\Event;

final class AfterTaskEvent extends Event
{
    public function __construct(
        public readonly TaskMetadata $task,
        public readonly TaskResult $result,
        public readonly float $duration,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->result->isSuccessful();
    }
}
