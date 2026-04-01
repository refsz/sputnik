<?php

declare(strict_types=1);

namespace Sputnik\DependencyInjection;

use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sputnik\Config\Configuration;
use Sputnik\Context\ContextManager;
use Sputnik\Environment\EnvironmentDetector;
use Sputnik\Event\ContextSwitchedEvent;
use Sputnik\Event\ListenerDiscovery;
use Sputnik\Executor\EnvironmentAwareExecutor;
use Sputnik\Executor\ExecutorInterface;
use Sputnik\Executor\ShellExecutor;
use Sputnik\Listener\RegenerateTemplatesOnContextSwitch;
use Sputnik\Listener\SwitchContextOnServices;
use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskRunner;
use Sputnik\Template\TemplateEngine;
use Sputnik\Variable\VariableResolver;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SputnikExtension extends CompilerExtension
{
    private ?TaskDiscovery $taskDiscovery = null;

    private ?ListenerDiscovery $listenerDiscovery = null;

    public function __construct(
        private readonly Configuration $sputnikConfig,
        private readonly string $workingDir,
    ) {
    }

    public function getConfigSchema(): Schema
    {
        return Expect::structure([]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $params = $builder->parameters;

        // Configuration (pre-loaded)
        $builder->addDefinition($this->prefix('config'))
            ->setFactory(Configuration::class, [$this->sputnikConfig->all()])
            ->setAutowired(true);

        // Logger
        $builder->addDefinition($this->prefix('logger'))
            ->setType(LoggerInterface::class)
            ->setFactory(NullLogger::class)
            ->setAutowired(true);

        // Shell Executor
        $builder->addDefinition($this->prefix('shellExecutor'))
            ->setFactory(ShellExecutor::class)
            ->setAutowired(true);

        // Context Manager
        $builder->addDefinition($this->prefix('contextManager'))
            ->setFactory(ContextManager::class, [
                'config' => $this->prefix('@config'),
                'workingDir' => $params['workingDir'],
            ])
            ->setAutowired(true);

        // Variable Resolver
        $builder->addDefinition($this->prefix('variableResolver'))
            ->setFactory(VariableResolver::class, [
                'config' => $this->prefix('@config'),
                'contextName' => $params['contextName'],
                'workingDir' => $params['workingDir'],
            ])
            ->setAutowired(true);

        // Task Discovery — pre-populate from already-scanned data to skip a second filesystem scan
        $taskDiscovery = $this->getTaskDiscovery();
        $builder->addDefinition($this->prefix('taskDiscovery'))
            ->setType(TaskDiscovery::class)
            ->setFactory(TaskDiscovery::class . '::withPreloadedData', [
                $taskDiscovery->discoverAll(),
                $taskDiscovery->getAliasMap(),
            ])
            ->setAutowired(true);

        // Listener Discovery — pre-populate from already-scanned data to skip a second filesystem scan
        $listenerDiscovery = $this->getListenerDiscovery();
        $builder->addDefinition($this->prefix('listenerDiscovery'))
            ->setType(ListenerDiscovery::class)
            ->setFactory(ListenerDiscovery::class . '::withPreloadedData', [
                $listenerDiscovery->discoverAll(),
            ])
            ->setAutowired(true);

        // Event Dispatcher
        $builder->addDefinition($this->prefix('eventDispatcher'))
            ->setType(EventDispatcherInterface::class)
            ->setFactory(EventDispatcher::class)
            ->setAutowired(true);

        // Template Engine
        $builder->addDefinition($this->prefix('templateEngine'))
            ->setFactory(TemplateEngine::class, [
                'config' => $this->prefix('@config'),
                'variables' => $this->prefix('@variableResolver'),
                'workingDir' => $params['workingDir'],
                'contextName' => $params['contextName'],
            ])
            ->setAutowired(true);

        // PSR Container Adapter
        $builder->addDefinition($this->prefix('psrContainer'))
            ->setType(ContainerInterface::class)
            ->setFactory(PsrContainerAdapter::class, ['@container'])
            ->setAutowired(true);

        // Environment Detector
        $envConfig = $this->sputnikConfig->all()['environment'] ?? [];
        $builder->addDefinition($this->prefix('environmentDetector'))
            ->setFactory(EnvironmentDetector::class, [
                'detection' => $envConfig['detection'] ?? null,
                'executor' => $envConfig['executor'] ?? null,
            ])
            ->setAutowired(true);

        // Task Runner
        $builder->addDefinition($this->prefix('taskRunner'))
            ->setFactory(TaskRunner::class, [
                'discovery' => $this->prefix('@taskDiscovery'),
                'variableResolver' => $this->prefix('@variableResolver'),
                'container' => $this->prefix('@psrContainer'),
                'logger' => $this->prefix('@logger'),
                'templateEngine' => $this->prefix('@templateEngine'),
                'eventDispatcher' => $this->prefix('@eventDispatcher'),
                'workingDir' => $params['workingDir'],
                'contextName' => $params['contextName'],
                'environmentDetector' => $this->prefix('@environmentDetector'),
            ])
            ->setAutowired(true);

        // Register discovered tasks
        $this->registerTasks();

        // Register discovered listeners
        $this->registerListeners();
    }

    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();
        $dispatcherDef = $builder->getDefinition($this->prefix('eventDispatcher'));

        if (!$dispatcherDef instanceof ServiceDefinition) {
            throw new \LogicException('Expected ServiceDefinition for eventDispatcher');
        }

        // --- Core listeners (hardwired, always run first) ---
        $this->registerCoreListeners($builder, $dispatcherDef);

        // --- User listeners (discovered, run after core) ---
        foreach ($builder->findByTag('sputnik.listener') as $serviceName => $tagValue) {
            $event = $tagValue['event'];
            $priority = $tagValue['priority'] ?? 0;

            $dispatcherDef->addSetup('addListener', [
                $event,
                [new Statement('@' . $serviceName), '__invoke'],
                $priority,
            ]);
        }
    }

    private function getTaskDiscovery(): TaskDiscovery
    {
        if (!$this->taskDiscovery instanceof TaskDiscovery) {
            $taskDirs = $this->sputnikConfig->getTaskDirectories($this->workingDir);
            $taskClasses = $this->sputnikConfig->getTaskClasses();
            $this->taskDiscovery = new TaskDiscovery($taskDirs, $taskClasses);
        }

        return $this->taskDiscovery;
    }

    private function getListenerDiscovery(): ListenerDiscovery
    {
        if (!$this->listenerDiscovery instanceof ListenerDiscovery) {
            $listenerDirs = $this->sputnikConfig->getTaskDirectories($this->workingDir);
            $this->listenerDiscovery = new ListenerDiscovery($listenerDirs);
        }

        return $this->listenerDiscovery;
    }

    private function registerCoreListeners(ContainerBuilder $builder, ServiceDefinition $dispatcherDef): void
    {
        // SwitchContextOnServices — updates VariableResolver + TemplateEngine state
        // Autowired so Nette resolves VariableResolver + TemplateEngine constructor params automatically
        $builder->addDefinition($this->prefix('listener.core.switchContext'))
            ->setFactory(SwitchContextOnServices::class)
            ->setAutowired(true);

        $dispatcherDef->addSetup('addListener', [
            ContextSwitchedEvent::class,
            [new Statement('@' . $this->prefix('listener.core.switchContext')), '__invoke'],
            \PHP_INT_MAX,
        ]);

        // RegenerateTemplatesOnContextSwitch — re-renders templates after switch
        // Autowired so Nette resolves TemplateEngine constructor param automatically
        $builder->addDefinition($this->prefix('listener.core.regenerateTemplates'))
            ->setFactory(RegenerateTemplatesOnContextSwitch::class)
            ->setAutowired(true);

        $dispatcherDef->addSetup('addListener', [
            ContextSwitchedEvent::class,
            [new Statement('@' . $this->prefix('listener.core.regenerateTemplates')), '__invoke'],
            \PHP_INT_MAX - 1,
        ]);
    }

    private function registerTasks(): void
    {
        $builder = $this->getContainerBuilder();

        // getTaskDiscovery() returns the same singleton used to seed the preloaded container service above,
        // so discoverAll() here re-uses cached results and does not trigger a second filesystem scan.
        foreach ($this->getTaskDiscovery()->discoverAll() as $metadata) {
            $serviceName = $this->prefix('task.' . $this->normalizeClassName($metadata->className));

            $builder->addDefinition($serviceName)
                ->setType($metadata->className)
                ->setFactory($metadata->className)
                ->addTag('sputnik.task', [
                    'name' => $metadata->getName(),
                    'class' => $metadata->className,
                ]);
        }
    }

    private function registerListeners(): void
    {
        $builder = $this->getContainerBuilder();

        // getListenerDiscovery() returns the same singleton used to seed the preloaded container service above,
        // so discoverAll() here re-uses cached results and does not trigger a second filesystem scan.
        foreach ($this->getListenerDiscovery()->discoverAll() as $metadata) {
            $serviceName = $this->prefix('listener.' . $this->normalizeClassName($metadata->className));

            // Skip if already registered
            if ($builder->hasDefinition($serviceName)) {
                continue;
            }

            $def = $builder->addDefinition($serviceName)
                ->setType($metadata->className)
                ->setFactory($metadata->className)
                ->setAutowired(false)
                ->addTag('sputnik.listener', [
                    'event' => $metadata->event,
                    'priority' => $metadata->priority,
                    'environment' => $metadata->environment,
                ]);

            // Inject EnvironmentAwareExecutor for listeners with environment
            if ($metadata->environment !== null) {
                $executorServiceName = $this->prefix('listener.executor.' . $this->normalizeClassName($metadata->className));
                $builder->addDefinition($executorServiceName)
                    ->setType(ExecutorInterface::class)
                    ->setFactory(EnvironmentAwareExecutor::class, [
                        'inner' => $this->prefix('@shellExecutor'),
                        'detector' => $this->prefix('@environmentDetector'),
                        'environment' => $metadata->environment,
                    ])
                    ->setAutowired(false);

                // Override the executor constructor param
                $def->setFactory($metadata->className, [
                    'executor' => '@' . $executorServiceName,
                ]);
            }
        }
    }

    private function normalizeClassName(string $className): string
    {
        return str_replace('\\', '_', strtolower($className));
    }
}
