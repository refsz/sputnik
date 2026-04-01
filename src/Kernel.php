<?php

declare(strict_types=1);

namespace Sputnik;

use Nette\DI\Container;
use Sputnik\Autoload\ClassmapAutoloader;
use Sputnik\Config\ConfigLoader;
use Sputnik\Config\Configuration;
use Sputnik\Console\Application;
use Sputnik\Console\Command\ContextListCommand;
use Sputnik\Console\Command\ContextSwitchCommand;
use Sputnik\Console\Command\InitCommand;
use Sputnik\Console\Command\RunCommand;
use Sputnik\Context\ContextManager;
use Sputnik\DependencyInjection\ContainerFactory;
use Sputnik\Environment\EnvironmentDetector;
use Sputnik\Event\ConfigLoadedEvent;
use Sputnik\Exception\RuntimeException as SputnikRuntimeException;
use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskRunner;
use Sputnik\Template\TemplateEngine;
use Sputnik\Variable\VariableResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class Kernel
{
    private Configuration $config;

    private Container $container;

    private string $workingDir;

    private string $contextName;

    private bool $debugMode;

    public function __construct(
        ?string $workingDir = null,
        ?string $contextName = null,
        bool $debugMode = false,
    ) {
        $cwdResult = getcwd();
        $this->workingDir = $workingDir ?? ($cwdResult !== false ? $cwdResult : throw new SputnikRuntimeException('Could not determine working directory'));
        $this->debugMode = $debugMode;

        $this->loadConfig();
        $this->registerTaskAutoloader();
        $this->initializeContextName($contextName);
        $this->buildContainer();
    }

    public function createApplication(): Application
    {
        $app = new Application();
        $app->setConfigFile($this->getConfigFileDisplay());
        $app->setTaskDiscovery($this->container->getByType(TaskDiscovery::class));
        // Get services from container
        $taskDiscovery = $this->container->getByType(TaskDiscovery::class);
        $taskRunner = $this->container->getByType(TaskRunner::class);
        $contextManager = $this->container->getByType(ContextManager::class);
        $eventDispatcher = $this->container->getByType(EventDispatcherInterface::class);
        // Add core commands
        $app->addCommand(new InitCommand());
        $app->addCommand(new RunCommand($taskDiscovery, $taskRunner));
        // Add context commands
        $app->addCommand(new ContextListCommand($contextManager));

        $templateEngine = $this->container->getByType(TemplateEngine::class);
        $app->addCommand(new ContextSwitchCommand($contextManager, $eventDispatcher, $templateEngine));
        // Register task commands (hidden from Symfony's list — shown in our custom section)
        foreach ($taskDiscovery->discoverAll() as $metadata) {
            if ($metadata->isHidden()) {
                continue;
            }

            $command = new TaskCommand($metadata, $taskRunner);
            $command->setHidden(true);
            $app->addCommand($command);
        }

        return $app;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    public function getContextManager(): ContextManager
    {
        return $this->container->getByType(ContextManager::class);
    }

    public function getDiscovery(): TaskDiscovery
    {
        return $this->container->getByType(TaskDiscovery::class);
    }

    public function getTaskRunner(): TaskRunner
    {
        return $this->container->getByType(TaskRunner::class);
    }

    public function getVariableResolver(): VariableResolver
    {
        return $this->container->getByType(VariableResolver::class);
    }

    public function getTemplateEngine(): TemplateEngine
    {
        return $this->container->getByType(TemplateEngine::class);
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->container->getByType(EventDispatcherInterface::class);
    }

    public function getEnvironmentDetector(): EnvironmentDetector
    {
        return $this->container->getByType(EnvironmentDetector::class);
    }

    private function getConfigFileDisplay(): string
    {
        $base = '.sputnik.dist.neon';
        $local = '.sputnik.neon';

        $display = $base;
        if (file_exists($this->workingDir . '/' . $local)) {
            $display .= ' + ' . $local;
        }

        return $display;
    }

    private function registerTaskAutoloader(): void
    {
        $directories = $this->config->getTaskDirectories($this->workingDir);

        if ($directories === []) {
            return;
        }

        $autoloader = new ClassmapAutoloader();
        $autoloader->scanDirectories($directories);
        $autoloader->register();
    }

    private function loadConfig(): void
    {
        $loader = new ConfigLoader($this->workingDir);

        if (!$loader->hasConfig()) {
            $this->config = new Configuration([]);

            return;
        }

        $this->config = $loader->load();
    }

    private function initializeContextName(?string $contextName): void
    {
        if ($contextName !== null) {
            $this->contextName = $contextName;

            return;
        }

        // Create temporary context manager to read persisted context
        $tempContextManager = new ContextManager($this->config, $this->workingDir);
        $this->contextName = $tempContextManager->getCurrentContext();
    }

    private function buildContainer(): void
    {
        $factory = new ContainerFactory(
            config: $this->config,
            workingDir: $this->workingDir,
            contextName: $this->contextName,
            debugMode: $this->debugMode,
        );

        $this->container = $factory->create();

        // Dispatch ConfigLoadedEvent after container is ready
        $this->container->getByType(EventDispatcherInterface::class)
            ->dispatch(new ConfigLoadedEvent($this->config));
    }
}
