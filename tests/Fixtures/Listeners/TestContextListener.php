<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\Listeners;

use Sputnik\Attribute\AsListener;
use Sputnik\Event\ContextSwitchedEvent;

#[AsListener(event: ContextSwitchedEvent::class, priority: 50)]
final class TestContextListener
{
    public static bool $wasCalled = false;
    public static ?ContextSwitchedEvent $lastEvent = null;

    public function __invoke(ContextSwitchedEvent $event): void
    {
        self::$wasCalled = true;
        self::$lastEvent = $event;
    }

    public static function reset(): void
    {
        self::$wasCalled = false;
        self::$lastEvent = null;
    }
}
