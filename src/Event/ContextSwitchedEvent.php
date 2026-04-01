<?php

declare(strict_types=1);

namespace Sputnik\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ContextSwitchedEvent extends Event
{
    public function __construct(
        public readonly string $previousContext,
        public readonly string $newContext,
    ) {
    }

    public function hasChanged(): bool
    {
        return $this->previousContext !== $this->newContext;
    }
}
