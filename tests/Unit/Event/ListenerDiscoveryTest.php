<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Sputnik\Event\ContextSwitchedEvent;
use Sputnik\Event\ListenerDiscovery;
use Sputnik\Event\ListenerMetadata;
use Sputnik\Tests\Fixtures\Listeners\TestContextListener;

final class ListenerDiscoveryTest extends TestCase
{
    private string $fixtureListenersDir;

    protected function setUp(): void
    {
        $this->fixtureListenersDir = \dirname(__DIR__, 2) . '/Fixtures/Listeners';
    }

    // --- discoverAll ---

    public function testDiscoverAllFindsListenersInDirectory(): void
    {
        $discovery = new ListenerDiscovery([$this->fixtureListenersDir]);

        $listeners = $discovery->discoverAll();

        $this->assertNotEmpty($listeners);

        $classNames = array_map(static fn (ListenerMetadata $m): string => $m->className, $listeners);
        $this->assertContains(TestContextListener::class, $classNames);
    }

    public function testDiscoverAllReturnsEmptyForEmptyDirectory(): void
    {
        $emptyDir = sys_get_temp_dir() . '/sputnik_test_empty_' . uniqid();
        mkdir($emptyDir, 0755, true);

        try {
            $discovery = new ListenerDiscovery([$emptyDir]);
            $this->assertSame([], $discovery->discoverAll());
        } finally {
            rmdir($emptyDir);
        }
    }

    public function testDiscoverAllHandlesNonExistentDirectory(): void
    {
        $discovery = new ListenerDiscovery(['/nonexistent/path/to/listeners']);

        // Should not throw — missing directories are silently skipped
        $listeners = $discovery->discoverAll();

        $this->assertSame([], $listeners);
    }

    public function testDiscoverAllIsIdempotent(): void
    {
        $discovery = new ListenerDiscovery([$this->fixtureListenersDir]);

        $first = $discovery->discoverAll();
        $second = $discovery->discoverAll();

        $this->assertSame($first, $second);
    }

    // --- priority sorting ---

    public function testListenersAreSortedByPriorityHighestFirst(): void
    {
        $discovery = new ListenerDiscovery([$this->fixtureListenersDir]);

        $listeners = $discovery->discoverAll();

        $this->assertGreaterThanOrEqual(2, \count($listeners), 'Need at least 2 listeners to test ordering');

        for ($i = 0; $i < \count($listeners) - 1; ++$i) {
            $this->assertGreaterThanOrEqual(
                $listeners[$i + 1]->priority,
                $listeners[$i]->priority,
                'Listeners must be ordered highest priority first',
            );
        }
    }

    // --- getListenersForEvent ---

    public function testGetListenersForEventFiltersCorrectly(): void
    {
        $discovery = new ListenerDiscovery([$this->fixtureListenersDir]);

        $listeners = $discovery->getListenersForEvent(ContextSwitchedEvent::class);

        $this->assertNotEmpty($listeners);

        foreach ($listeners as $listener) {
            $this->assertSame(ContextSwitchedEvent::class, $listener->event);
        }
    }

    public function testGetListenersForEventReturnsEmptyForUnknownEvent(): void
    {
        $discovery = new ListenerDiscovery([$this->fixtureListenersDir]);

        $listeners = $discovery->getListenersForEvent('App\Event\SomeOtherEvent');

        $this->assertSame([], array_values($listeners));
    }

    public function testGetListenersForEventTriggersDiscovery(): void
    {
        // Start with no directories — getListenersForEvent must still work
        $discovery = new ListenerDiscovery([]);

        $listeners = $discovery->getListenersForEvent(ContextSwitchedEvent::class);

        $this->assertSame([], array_values($listeners));
    }

    // --- withPreloadedData ---

    public function testWithPreloadedDataBypassesFilesystemScanning(): void
    {
        $preloaded = [
            new ListenerMetadata(
                className: 'App\Listener\FakeListener',
                event: ContextSwitchedEvent::class,
                priority: 100,
            ),
        ];

        $discovery = ListenerDiscovery::withPreloadedData($preloaded);

        // discoverAll must return exactly the preloaded data without scanning
        $result = $discovery->discoverAll();

        $this->assertCount(1, $result);
        $this->assertSame('App\Listener\FakeListener', $result[0]->className);
        $this->assertSame(100, $result[0]->priority);
    }

    public function testWithPreloadedDataIsAvailableViaGetListenersForEvent(): void
    {
        $preloaded = [
            new ListenerMetadata(
                className: 'App\Listener\FakeListener',
                event: ContextSwitchedEvent::class,
                priority: 0,
            ),
            new ListenerMetadata(
                className: 'App\Listener\OtherListener',
                event: 'App\Event\OtherEvent',
                priority: 0,
            ),
        ];

        $discovery = ListenerDiscovery::withPreloadedData($preloaded);

        $filtered = $discovery->getListenersForEvent(ContextSwitchedEvent::class);

        $this->assertCount(1, $filtered);
        $this->assertSame('App\Listener\FakeListener', array_values($filtered)[0]->className);
    }

    public function testWithPreloadedDataEmptyList(): void
    {
        $discovery = ListenerDiscovery::withPreloadedData([]);

        $this->assertSame([], $discovery->discoverAll());
    }
}
