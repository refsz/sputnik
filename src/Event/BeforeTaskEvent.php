<?php

declare(strict_types=1);

namespace Sputnik\Event;

use Sputnik\Task\TaskMetadata;
use Symfony\Contracts\EventDispatcher\Event;

final class BeforeTaskEvent extends Event
{
    private bool $cancelled = false;

    private ?string $cancelReason = null;

    /**
     * @param array<int|string, mixed> $arguments
     * @param array<string, mixed>     $options
     */
    public function __construct(
        public readonly TaskMetadata $task,
        public readonly array $arguments,
        public readonly array $options,
    ) {
    }

    /**
     * Cancel the task execution.
     */
    public function cancel(string $reason = 'Cancelled by listener'): void
    {
        $this->cancelled = true;
        $this->cancelReason = $reason;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }
}
