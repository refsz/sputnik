<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\Task;

use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Sputnik\Attribute\Task;
use Sputnik\Config\Configuration;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskResult;
use Sputnik\Task\TaskRunner;
use Sputnik\Template\TemplateEngine;
use Sputnik\Tests\Support\Doubles\InMemoryVariableResolver;
use Sputnik\Tests\Support\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TaskContextTemplateRenderTest extends TestCase
{
    private string $tempDir;

    private InMemoryVariableResolver $variables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = $this->createTempDir();
        $this->variables = new InMemoryVariableResolver();

        mkdir($this->tempDir . '/templates', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
        parent::tearDown();
    }

    public function testRenderProducesTheSameOutputAsATemplateFile(): void
    {
        $source = "name: {{ appDomain }}\nphp: {{ phpVersion | \"8.3\" }}";
        file_put_contents($this->tempDir . '/templates/config.yaml.dist', $source);
        $this->variables->set('appDomain', 'drupal.test');

        $rendered = $this->runTaskRendering($source);

        $this->assertSame("name: drupal.test\nphp: 8.3", $rendered);
        $this->assertSame(file_get_contents($this->tempDir . '/config.yaml'), $rendered);
    }

    public function testRenderSeesRuntimeVariableOverrides(): void
    {
        file_put_contents($this->tempDir . '/templates/config.yaml.dist', 'name: {{ appDomain }}');
        $this->variables->set('appDomain', 'drupal.test');

        $rendered = $this->runTaskRendering(
            'name: {{ appDomain }}',
            runtimeVariables: ['appDomain' => 'override.test'],
        );

        $this->assertSame('name: override.test', $rendered);
    }

    /**
     * @param array<string, mixed> $runtimeVariables
     */
    private function runTaskRendering(string $template, array $runtimeVariables = []): string
    {
        $task = new class($template) implements TaskInterface {
            public ?string $rendered = null;

            public function __construct(private readonly string $template)
            {
            }

            public function __invoke(TaskContext $context): TaskResult
            {
                $this->rendered = $context->render($this->template);

                return TaskResult::success();
            }
        };

        $result = $this->createRunner($task)->run(
            'test:render',
            runtimeVariables: $runtimeVariables,
        );

        $this->assertTrue($result->isSuccessful(), (string) $result->message);

        if ($task->rendered === null) {
            self::fail('Task did not render the template');
        }

        return $task->rendered;
    }

    private function createRunner(TaskInterface $task): TaskRunner
    {
        $metadata = new TaskMetadata($task::class, new Task(name: 'test:render'));

        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($task);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static fn (object $event): object => $event);

        $config = new Configuration([
            'templates' => [
                'config' => [
                    'src' => 'templates/config.yaml.dist',
                    'dist' => 'config.yaml',
                ],
            ],
        ]);

        return new TaskRunner(
            discovery: $discovery,
            variableResolver: $this->variables,
            container: $container,
            logger: new NullLogger(),
            templateEngine: new TemplateEngine($config, $this->variables, $this->tempDir, 'local'),
            eventDispatcher: $eventDispatcher,
            workingDir: $this->tempDir,
            contextName: 'local',
        );
    }
}
