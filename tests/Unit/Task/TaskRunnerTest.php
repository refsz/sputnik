<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Sputnik\Attribute\Option;
use Sputnik\Attribute\Task;
use Sputnik\Console\SputnikOutput;
use Sputnik\Environment\EnvironmentDetector;
use Sputnik\Event\AfterTaskEvent;
use Sputnik\Event\BeforeTaskEvent;
use Sputnik\Event\TaskFailedEvent;
use Sputnik\Event\TemplateRenderedEvent;
use Sputnik\Exception\RuntimeException as SputnikRuntimeException;
use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskNotFoundException;
use Sputnik\Task\TaskResult;
use Sputnik\Task\TaskRunner;
use Sputnik\Template\TemplateConfig;
use Sputnik\Template\TemplateEngine;
use Sputnik\Tests\Support\Doubles\InMemoryVariableResolver;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TaskRunnerTest extends TestCase
{
    private TaskDiscovery $discovery;
    private ContainerInterface $container;
    private EventDispatcherInterface $eventDispatcher;
    private TemplateEngine $templateEngine;
    private InMemoryVariableResolver $variables;

    protected function setUp(): void
    {
        $this->discovery = $this->createMock(TaskDiscovery::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->templateEngine = $this->createMock(TemplateEngine::class);
        $this->variables = new InMemoryVariableResolver();

        $this->templateEngine->method('getTemplatesForContext')->willReturn([]);
        $this->eventDispatcher->method('dispatch')->willReturnCallback(static fn ($e) => $e);
    }

    public function testRunSuccessfulTask(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success('done'));

        $result = $this->createRunner()->run('test:task');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('done', $result->message);
        $this->assertNotNull($result->duration);
    }

    public function testRunTaskNotFoundThrows(): void
    {
        $this->discovery->method('getTask')->willReturn(null);

        $this->expectException(TaskNotFoundException::class);
        $this->createRunner()->run('nonexistent');
    }

    public function testRunDispatchesBeforeAndAfterEvents(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $dispatched = [];
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatched) {
                $dispatched[] = $event::class;

                return $event;
            });

        $this->createRunner()->run('test:task');

        $this->assertContains(BeforeTaskEvent::class, $dispatched);
        $this->assertContains(AfterTaskEvent::class, $dispatched);
    }

    public function testRunDispatchesFailedEventOnException(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubThrowingTask(new SputnikRuntimeException('boom'));

        $dispatched = [];
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatched) {
                $dispatched[] = $event::class;

                return $event;
            });

        $result = $this->createRunner()->run('test:task');

        $this->assertFalse($result->isSuccessful());
        $this->assertContains(TaskFailedEvent::class, $dispatched);
    }

    public function testRunCancelledByBeforeEvent(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->container->method('has')->willReturn(false);

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher->method('dispatch')
            ->willReturnCallback(static function ($event) {
                if ($event instanceof BeforeTaskEvent) {
                    $event->cancel('blocked by test');
                }

                return $event;
            });

        $result = $this->createRunner()->run('test:task');

        $this->assertTrue($result->isSkipped());
        $this->assertStringContainsString('blocked by test', $result->message);
    }

    public function testExceptionReturnsFailureResult(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubThrowingTask(new SputnikRuntimeException('task error'));

        $result = $this->createRunner()->run('test:task');

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('task error', $result->message);
        $this->assertNotNull($result->duration);
    }

    public function testOptionTypeCoercionInt(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'), [
            new Option(name: 'count', type: 'int'),
        ]);
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $result = $this->createRunner()->run('test:task', [], ['count' => '42']);
        $this->assertTrue($result->isSuccessful());
    }

    public function testOptionTypeCoercionInvalidIntFails(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'), [
            new Option(name: 'count', type: 'int'),
        ]);
        $this->discovery->method('getTask')->willReturn($metadata);

        $result = $this->createRunner()->run('test:task', [], ['count' => 'abc']);
        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('count', $result->message);
    }

    public function testOptionTypeCoercionBoolFromString(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'), [
            new Option(name: 'debug', type: 'bool'),
        ]);
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $result = $this->createRunner()->run('test:task', [], ['debug' => 'true']);
        $this->assertTrue($result->isSuccessful());
    }

    public function testOptionTypeCoercionBoolPassthrough(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'), [
            new Option(name: 'debug', type: 'bool'),
        ]);
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $result = $this->createRunner()->run('test:task', [], ['debug' => true]);
        $this->assertTrue($result->isSuccessful());
    }

    public function testOptionChoicesValidationPasses(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'), [
            new Option(name: 'env', choices: ['dev', 'staging', 'prod']),
        ]);
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $result = $this->createRunner()->run('test:task', [], ['env' => 'staging']);
        $this->assertTrue($result->isSuccessful());
    }

    public function testOptionChoicesValidationFails(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'), [
            new Option(name: 'env', choices: ['dev', 'staging', 'prod']),
        ]);
        $this->discovery->method('getTask')->willReturn($metadata);

        $result = $this->createRunner()->run('test:task', [], ['env' => 'invalid']);
        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('must be one of', $result->message);
    }

    public function testOptionNullValueSkipsValidation(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'), [
            new Option(name: 'env', type: 'string', choices: ['dev', 'prod']),
        ]);
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $result = $this->createRunner()->run('test:task', [], ['env' => null]);
        $this->assertTrue($result->isSuccessful());
    }

    public function testOptionCoercionBeforeChoiceValidation(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'), [
            new Option(name: 'level', type: 'int', choices: [1, 2, 3]),
        ]);
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $result = $this->createRunner()->run('test:task', [], ['level' => '2']);
        $this->assertTrue($result->isSuccessful());
    }

    public function testOptionNullTypeMeansNoCoercion(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'), [
            new Option(name: 'raw'),
        ]);
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $result = $this->createRunner()->run('test:task', [], ['raw' => '42']);
        $this->assertTrue($result->isSuccessful());
    }

    public function testRunWithSputnikOutputSetsSteps(): void
    {
        // Use a real class so ReflectionClass can find its file
        $metadata = new TaskMetadata(self::class, new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success('done'));

        $sputnikOutput = $this->createMock(SputnikOutput::class);
        $sputnikOutput->expects($this->once())->method('setTotalSteps');

        $result = $this->createRunner()->run('test:task', [], [], null, [], $sputnikOutput);

        $this->assertTrue($result->isSuccessful());
    }

    public function testRunWithOutputCreatesConsoleLogger(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success('done'));

        $output = $this->createMock(OutputInterface::class);
        $output->method('isDebug')->willReturn(false);
        $output->method('getVerbosity')->willReturn(0);
        $output->method('writeln');

        $result = $this->createRunner()->run('test:task', [], [], $output);

        $this->assertTrue($result->isSuccessful());
    }

    public function testRunWithEnvironmentDetector(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success('done'));

        // EnvironmentDetector is final — use a real instance with no detection script
        $envDetector = new EnvironmentDetector();

        $result = $this->createRunnerWithDetector($envDetector)->run('test:task');

        $this->assertTrue($result->isSuccessful());
    }

    public function testRunWithRuntimeVariables(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success('done'));

        $result = $this->createRunner()->run('test:task', [], [], null, ['MY_VAR' => 'hello']);

        $this->assertTrue($result->isSuccessful());
    }

    public function testRenderTemplatesLogsWarningOnError(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $this->templateEngine = $this->createMock(TemplateEngine::class);
        $this->templateEngine->method('getTemplatesForContext')
            ->willReturn(['env' => new TemplateConfig('env', 'src', 'dist')]);
        $this->templateEngine->method('renderAll')
            ->willReturn([
                'env' => ['written' => false, 'path' => '/tmp/env', 'skipped' => true, 'error' => 'some error'],
            ]);
        $this->templateEngine->method('getTemplate')
            ->willReturn(new TemplateConfig('env', 'src', 'dist'));

        // Should succeed despite template warning
        $result = $this->createRunner()->run('test:task');
        $this->assertTrue($result->isSuccessful());
    }

    public function testRenderTemplatesLogsSkippedReason(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $this->templateEngine = $this->createMock(TemplateEngine::class);
        $this->templateEngine->method('getTemplatesForContext')
            ->willReturn(['env' => new TemplateConfig('env', 'src', 'dist')]);
        $this->templateEngine->method('renderAll')
            ->willReturn([
                'env' => ['written' => false, 'path' => '/tmp/env', 'skipped' => true, 'reason' => 'file unchanged'],
            ]);
        $this->templateEngine->method('getTemplate')
            ->willReturn(new TemplateConfig('env', 'src', 'dist'));

        $result = $this->createRunner()->run('test:task');
        $this->assertTrue($result->isSuccessful());
    }

    public function testRenderTemplatesDispatchesEventForWrittenTemplate(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $this->templateEngine = $this->createMock(TemplateEngine::class);
        $this->templateEngine->method('getTemplatesForContext')
            ->willReturn(['env' => new TemplateConfig('env', 'src', 'dist')]);
        $this->templateEngine->method('renderAll')
            ->willReturn([
                'env' => ['written' => true, 'path' => '/tmp/env', 'skipped' => false],
            ]);
        $this->templateEngine->method('getTemplate')
            ->willReturn(new TemplateConfig('env', 'src', 'dist'));

        $dispatched = [];
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatched) {
                $dispatched[] = $event::class;

                return $event;
            });

        $this->createRunner()->run('test:task');

        $this->assertContains(TemplateRenderedEvent::class, $dispatched);
    }

    public function testTemplatesRenderedOnlyOnce(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
        $this->discovery->method('getTask')->willReturn($metadata);
        $this->stubTask(TaskResult::success());

        $renderCount = 0;
        $this->templateEngine = $this->createMock(TemplateEngine::class);
        $this->templateEngine->method('getTemplatesForContext')
            ->willReturn(['env' => new TemplateConfig('env', 'src', 'dist')]);
        $this->templateEngine->method('renderAll')
            ->willReturnCallback(static function () use (&$renderCount) {
                ++$renderCount;

                return [];
            });

        $runner = $this->createRunner();
        $runner->run('test:task');
        $runner->run('test:task');

        $this->assertSame(1, $renderCount);
    }

    private function createRunner(): TaskRunner
    {
        return new TaskRunner(
            discovery: $this->discovery,
            variableResolver: $this->variables,
            container: $this->container,
            logger: new NullLogger(),
            templateEngine: $this->templateEngine,
            eventDispatcher: $this->eventDispatcher,
            workingDir: sys_get_temp_dir(),
            contextName: 'test',
        );
    }

    private function createRunnerWithDetector(EnvironmentDetector $detector): TaskRunner
    {
        return new TaskRunner(
            discovery: $this->discovery,
            variableResolver: $this->variables,
            container: $this->container,
            logger: new NullLogger(),
            templateEngine: $this->templateEngine,
            eventDispatcher: $this->eventDispatcher,
            workingDir: sys_get_temp_dir(),
            contextName: 'test',
            environmentDetector: $detector,
        );
    }

    private function stubTask(TaskResult $result): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('__invoke')->willReturn($result);
        $this->container->method('get')->willReturn($task);
        $this->container->method('has')->willReturn(false);
    }

    private function stubThrowingTask(\Throwable $e): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('__invoke')->willThrowException($e);
        $this->container->method('get')->willReturn($task);
        $this->container->method('has')->willReturn(false);
    }
}
