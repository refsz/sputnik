<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Sputnik\Event\ContextSwitchedEvent;

final class ContextSwitchedEventTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $event = new ContextSwitchedEvent('local', 'production');

        $this->assertSame('local', $event->previousContext);
        $this->assertSame('production', $event->newContext);
    }

    public function testHasChangedReturnsTrueWhenDifferent(): void
    {
        $event = new ContextSwitchedEvent('local', 'production');

        $this->assertTrue($event->hasChanged());
    }

    public function testHasChangedReturnsFalseWhenSame(): void
    {
        $event = new ContextSwitchedEvent('local', 'local');

        $this->assertFalse($event->hasChanged());
    }
}
