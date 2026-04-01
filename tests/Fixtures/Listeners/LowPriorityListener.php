<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\Listeners;

use Sputnik\Attribute\AsListener;
use Sputnik\Event\ContextSwitchedEvent;

#[AsListener(event: ContextSwitchedEvent::class, priority: 10)]
final class LowPriorityListener
{
    public function __invoke(ContextSwitchedEvent $event): void
    {
    }
}
