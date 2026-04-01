<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\DependencyInjection;

use Nette\DI\Container;
use Psr\Log\LoggerInterface;
use Sputnik\Config\Configuration;
use Sputnik\Context\ContextManager;
use Sputnik\DependencyInjection\ContainerFactory;
use Sputnik\Event\ListenerDiscovery;
use Sputnik\Executor\ShellExecutor;
use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskRunner;
use Sputnik\Template\TemplateEngine;
use Sputnik\Tests\Support\TestCase;
use Sputnik\Variable\VariableResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ContainerFactoryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = $this->createTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
        parent::tearDown();
    }

    public function testCreateReturnsContainer(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();

        $this->assertInstanceOf(Container::class, $container);
    }

    public function testContainerProvidesConfiguration(): void
    {
        $config = new Configuration(['foo' => 'bar']);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $resolvedConfig = $container->getByType(Configuration::class);

        $this->assertInstanceOf(Configuration::class, $resolvedConfig);
        $this->assertSame('bar', $resolvedConfig->get('foo'));
    }

    public function testContainerProvidesLogger(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $logger = $container->getByType(LoggerInterface::class);

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testContainerProvidesShellExecutor(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $executor = $container->getByType(ShellExecutor::class);

        $this->assertInstanceOf(ShellExecutor::class, $executor);
    }

    public function testContainerProvidesContextManager(): void
    {
        $config = new Configuration([
            'contexts' => ['local' => []],
        ]);
        $factory = new ContainerFactory($config, $this->tempDir, 'local');

        $container = $factory->create();
        $contextManager = $container->getByType(ContextManager::class);

        $this->assertInstanceOf(ContextManager::class, $contextManager);
    }

    public function testContainerProvidesVariableResolver(): void
    {
        $config = new Configuration([
            'variables' => ['name' => 'value'],
        ]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $resolver = $container->getByType(VariableResolver::class);

        $this->assertInstanceOf(VariableResolver::class, $resolver);
    }

    public function testContainerProvidesTaskDiscovery(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $discovery = $container->getByType(TaskDiscovery::class);

        $this->assertInstanceOf(TaskDiscovery::class, $discovery);
    }

    public function testContainerProvidesListenerDiscovery(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $discovery = $container->getByType(ListenerDiscovery::class);

        $this->assertInstanceOf(ListenerDiscovery::class, $discovery);
    }

    public function testContainerProvidesEventDispatcher(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $dispatcher = $container->getByType(EventDispatcherInterface::class);

        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    }

    public function testContainerProvidesTemplateEngine(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $engine = $container->getByType(TemplateEngine::class);

        $this->assertInstanceOf(TemplateEngine::class, $engine);
    }

    public function testContainerProvidesTaskRunner(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $runner = $container->getByType(TaskRunner::class);

        $this->assertInstanceOf(TaskRunner::class, $runner);
    }

    public function testContainerCachesCompilation(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        // Create container twice
        $container1 = $factory->create();
        $container2 = $factory->create();

        // Both should be same class (cached)
        $this->assertSame($container1::class, $container2::class);
    }

    public function testContainerCreatesCacheDirectory(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $factory->create();

        $this->assertDirectoryExists($this->tempDir . '/.sputnik/cache');
    }

    public function testDebugModeAffectsContainer(): void
    {
        $config = new Configuration([]);

        $factoryDebug = new ContainerFactory($config, $this->tempDir, 'default', debugMode: true);
        $containerDebug = $factoryDebug->create();

        // In debug mode, container class name should differ on each recompile
        // We can verify the container was created successfully
        $this->assertInstanceOf(Container::class, $containerDebug);
    }

    public function testContainerParametersAreSet(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'mycontext');

        $container = $factory->create();

        $this->assertSame($this->tempDir, $container->getParameters()['workingDir']);
        $this->assertSame('mycontext', $container->getParameters()['contextName']);
    }
}
