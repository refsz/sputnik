<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\DependencyInjection;

use Sputnik\Config\Configuration;
use Sputnik\DependencyInjection\ContainerFactory;
use Sputnik\Event\ContextSwitchedEvent;
use Sputnik\Tests\Fixtures\Listeners\TestContextListener;
use Sputnik\Tests\Fixtures\Tasks\SimpleTask;
use Sputnik\Tests\Support\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SputnikExtensionTest extends TestCase
{
    private string $tempDir;
    private string $fixturesTasksDir;
    private string $fixturesListenersDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = $this->createTempDir();
        $this->fixturesTasksDir = \dirname(__DIR__, 2) . '/Fixtures/Tasks';
        $this->fixturesListenersDir = \dirname(__DIR__, 2) . '/Fixtures/Listeners';

        // Reset static state
        TestContextListener::reset();
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
        TestContextListener::reset();
        parent::tearDown();
    }

    public function testTasksAreRegisteredInContainer(): void
    {
        $config = new Configuration([
            'tasks' => ['directories' => [$this->fixturesTasksDir]],
        ]);

        $factory = new ContainerFactory($config, $this->tempDir, 'default');
        $container = $factory->create();

        // Task should be registered with tag
        $taggedServices = $container->findByTag('sputnik.task');
        $taskNames = array_map(static fn ($tag) => $tag['name'], $taggedServices);

        $this->assertContains('test:simple', $taskNames);
    }

    public function testListenersAreRegisteredInContainer(): void
    {
        $config = new Configuration([
            'tasks' => ['directories' => [$this->fixturesListenersDir]],
        ]);

        $factory = new ContainerFactory($config, $this->tempDir, 'default');
        $container = $factory->create();

        // Listener should be registered with tag
        $taggedServices = $container->findByTag('sputnik.listener');

        $this->assertNotEmpty($taggedServices);

        // Verify our test listener is registered
        $listenerEvents = array_map(static fn ($tag) => $tag['event'], $taggedServices);
        $this->assertContains(ContextSwitchedEvent::class, $listenerEvents);
    }

    public function testListenersAreWiredToEventDispatcher(): void
    {
        $config = new Configuration([
            'tasks' => ['directories' => [$this->fixturesListenersDir]],
        ]);

        $factory = new ContainerFactory($config, $this->tempDir, 'default');
        $container = $factory->create();

        // Get dispatcher and dispatch event
        $dispatcher = $container->getByType(EventDispatcherInterface::class);
        $event = new ContextSwitchedEvent('old', 'new');
        $dispatcher->dispatch($event);

        // Listener should have been called
        $this->assertTrue(TestContextListener::$wasCalled);
        $this->assertSame('old', TestContextListener::$lastEvent->previousContext);
        $this->assertSame('new', TestContextListener::$lastEvent->newContext);
    }

    public function testBuiltinListenersAreRegistered(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');
        $container = $factory->create();

        // Core listeners are hardwired (not tagged) — verify by service name
        $this->assertTrue(
            $container->hasService('sputnik.listener.core.regenerateTemplates'),
            'Core RegenerateTemplatesOnContextSwitch listener should be registered',
        );
        $this->assertTrue(
            $container->hasService('sputnik.listener.core.switchContext'),
            'Core SwitchContextOnServices listener should be registered',
        );
    }

    public function testMultipleTaskDirectoriesAreScanned(): void
    {
        $config = new Configuration([
            'tasks' => ['directories' => [$this->fixturesTasksDir, $this->fixturesListenersDir]],
        ]);

        $factory = new ContainerFactory($config, $this->tempDir, 'default');
        $container = $factory->create();

        // Tasks from fixtures dir
        $taggedServices = $container->findByTag('sputnik.task');
        $taskNames = array_map(static fn ($tag) => $tag['name'], $taggedServices);

        $this->assertContains('test:simple', $taskNames);
        $this->assertContains('test:with-options', $taskNames);

        // Listeners from listeners dir
        $listeners = $container->findByTag('sputnik.listener');
        $this->assertNotEmpty($listeners);
    }

    public function testServicesAreAutowired(): void
    {
        $config = new Configuration([
            'tasks' => ['directories' => [$this->fixturesTasksDir]],
        ]);

        $factory = new ContainerFactory($config, $this->tempDir, 'default');
        $container = $factory->create();

        // Task should be resolvable (autowiring should work)
        $taggedServices = $container->findByTag('sputnik.task');

        $taskServiceName = null;
        foreach ($taggedServices as $name => $tag) {
            if ($tag['name'] === 'test:simple') {
                $taskServiceName = $name;
                break;
            }
        }

        $this->assertNotNull($taskServiceName);

        // Get the service - this will fail if autowiring doesn't work
        $task = $container->getService($taskServiceName);
        $this->assertInstanceOf(SimpleTask::class, $task);
    }

    public function testListenerPriorityIsRegistered(): void
    {
        $config = new Configuration([
            'tasks' => ['directories' => [$this->fixturesListenersDir]],
        ]);

        $factory = new ContainerFactory($config, $this->tempDir, 'default');
        $container = $factory->create();

        $taggedServices = $container->findByTag('sputnik.listener');

        // Find our test listener tag (TestContextListener has priority 50)
        $listenerTag = null;
        foreach ($taggedServices as $serviceName => $tag) {
            // Find specifically TestContextListener by service name
            if (str_contains($serviceName, 'testcontextlistener')) {
                $listenerTag = $tag;
                break;
            }
        }

        $this->assertNotNull($listenerTag, 'TestContextListener should be registered');
        $this->assertSame(ContextSwitchedEvent::class, $listenerTag['event']);
        $this->assertSame(50, $listenerTag['priority']);
    }
}
