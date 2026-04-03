<?php

declare(strict_types=1);

namespace Sputnik\Task;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Sputnik\Console\ConsoleLogger;
use Sputnik\Console\ShellCallCounter;
use Sputnik\Console\SputnikOutput;
use Sputnik\Environment\EnvironmentDetector;
use Sputnik\Event\AfterTaskEvent;
use Sputnik\Event\BeforeTaskEvent;
use Sputnik\Event\TaskFailedEvent;
use Sputnik\Event\TemplateRenderedEvent;
use Sputnik\Exception\ShouldNotHappenException;
use Sputnik\Executor\EnvironmentAwareExecutor;
use Sputnik\Executor\ShellExecutor;
use Sputnik\Template\TemplateConfig;
use Sputnik\Template\TemplateEngine;
use Sputnik\Variable\VariableResolverInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TaskRunner implements TaskRunnerInterface
{
    private bool $templatesRendered = false;

    private readonly OptionCoercer $optionCoercer;

    public function __construct(
        private readonly TaskDiscovery $discovery,
        private readonly VariableResolverInterface $variableResolver,
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
        private readonly TemplateEngine $templateEngine,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $workingDir,
        private readonly string $contextName = 'local',
        private readonly ?EnvironmentDetector $environmentDetector = null,
    ) {
        $this->optionCoercer = new OptionCoercer();
    }

    /**
     * @param array<int|string, mixed> $arguments
     * @param array<string, mixed>     $options
     * @param array<string, mixed>     $runtimeVariables
     */
    public function run(
        string $taskName,
        array $arguments = [],
        array $options = [],
        ?OutputInterface $output = null,
        array $runtimeVariables = [],
        ?SputnikOutput $sputnikOutput = null,
    ): TaskResult {
        $metadata = $this->discovery->getTask($taskName);

        if (!$metadata instanceof TaskMetadata) {
            throw TaskNotFoundException::forTask($taskName);
        }

        // Create logger - use ConsoleLogger if output provided, otherwise fallback
        $logger = $output instanceof OutputInterface
            ? new ConsoleLogger($output)
            : $this->logger;

        // Render templates once before first task execution
        if (!$this->templatesRendered) {
            $this->renderTemplates($logger);
            $this->templatesRendered = true;
        }

        $startTime = microtime(true);
        try {
            $context = $this->createContext($metadata, $arguments, $options, $logger, $output, $runtimeVariables, $sputnikOutput);

            $beforeEvent = new BeforeTaskEvent($metadata, $arguments, $options);
            $this->eventDispatcher->dispatch($beforeEvent);

            if ($beforeEvent->isCancelled()) {
                return TaskResult::skipped($beforeEvent->getCancelReason() ?? 'Cancelled by listener');
            }

            $task = $this->container->get($metadata->className);

            if (!$task instanceof TaskInterface) {
                throw new ShouldNotHappenException(\sprintf(
                    "Container returned %s for task '%s', expected TaskInterface",
                    get_debug_type($task),
                    $taskName,
                ));
            }

            if ($sputnikOutput instanceof SputnikOutput) {
                $reflectionFile = (new \ReflectionClass($metadata->className))->getFileName();
                if ($reflectionFile !== false) {
                    $totalSteps = ShellCallCounter::count($reflectionFile);
                    $sputnikOutput->setTotalSteps($totalSteps);
                }
            }

            $startTime = microtime(true);
            $logger->info('Running task: ' . $taskName); // overwrites the outer $startTime to record task start precisely
            $result = $task($context);
            $duration = microtime(true) - $startTime;

            $this->eventDispatcher->dispatch(new AfterTaskEvent($metadata, $result, $duration));

            return $result->withDuration($duration);
        } catch (\Throwable $throwable) {
            $duration = microtime(true) - $startTime;
            $logger->error('Task failed: ' . $throwable->getMessage());
            $this->eventDispatcher->dispatch(new TaskFailedEvent($metadata, $throwable));

            return TaskResult::failure($throwable->getMessage())->withDuration($duration);
        }
    }

    public function getContextName(): string
    {
        return $this->contextName;
    }

    /**
     * @param array<int|string, mixed> $arguments
     * @param array<string, mixed>     $options
     * @param array<string, mixed>     $runtimeVariables
     */
    private function createContext(
        TaskMetadata $metadata,
        array $arguments,
        array $options,
        LoggerInterface $logger,
        ?OutputInterface $output = null,
        array $runtimeVariables = [],
        ?SputnikOutput $sputnikOutput = null,
    ): TaskContext {
        // Merge defaults with provided values
        $resolvedOptions = $this->optionCoercer->resolveOptions($metadata, $options);
        $resolvedArguments = $this->optionCoercer->resolveArguments($metadata, $arguments);

        $shellExecutor = match (true) {
            $sputnikOutput instanceof SputnikOutput => new ShellExecutor(sputnikOutput: $sputnikOutput),
            $output instanceof OutputInterface => new ShellExecutor($output),
            default => new ShellExecutor(),
        };

        // Wrap executor for environment-aware routing
        if ($this->environmentDetector instanceof EnvironmentDetector) {
            $shellExecutor = new EnvironmentAwareExecutor($shellExecutor, $this->environmentDetector, $metadata->getEnvironment());
        }

        // Apply runtime variable overrides if provided
        $variables = $runtimeVariables === []
            ? $this->variableResolver
            : $this->variableResolver->withOverrides($runtimeVariables);

        return new TaskContext(
            variables: $variables,
            options: $resolvedOptions,
            arguments: $resolvedArguments,
            contextName: $this->contextName,
            workingDir: $this->workingDir,
            logger: $logger,
            shellExecutor: $shellExecutor,
            taskRunner: $this,
            output: $output,
            sputnikOutput: $sputnikOutput,
            runtimeVariables: $runtimeVariables,
        );
    }

    private function renderTemplates(LoggerInterface $logger): void
    {
        $templates = $this->templateEngine->getTemplatesForContext();

        if ($templates === []) {
            return;
        }

        $logger->debug('Rendering templates for context: ' . $this->contextName);

        $results = $this->templateEngine->renderAll(force: true);

        foreach ($results as $name => $result) {
            $templateConfig = $this->templateEngine->getTemplate($name);

            if (isset($result['error'])) {
                $logger->warning(\sprintf("Template '%s' failed: %s", $name, $result['error']));
            } elseif ($result['written']) {
                $logger->debug(\sprintf("Template '%s' written to %s", $name, $result['path']));
            } else {
                $logger->debug(\sprintf("Template '%s' skipped", $name) . (isset($result['reason']) ? ': ' . $result['reason'] : ''));
            }

            // Dispatch event for each template
            if ($templateConfig instanceof TemplateConfig) {
                $this->eventDispatcher->dispatch(new TemplateRenderedEvent(
                    $templateConfig,
                    $result['path'],
                    written: $result['written'],
                    skipReason: $result['reason'] ?? $result['error'] ?? null,
                ));
            }
        }
    }
}
