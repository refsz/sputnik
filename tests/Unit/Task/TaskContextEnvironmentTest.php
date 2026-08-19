<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sputnik\Environment\EnvironmentDetector;
use Sputnik\Executor\EnvironmentAwareExecutor;
use Sputnik\Executor\ExecutionResult;
use Sputnik\Executor\ExecutorInterface;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskRunnerInterface;
use Sputnik\Template\TemplateParser;
use Sputnik\Template\TemplateRenderer;
use Sputnik\Variable\VariableResolverInterface;

final class TaskContextEnvironmentTest extends TestCase
{
    public function testExecWrapsForContainerOnHost(): void
    {
        $captured = null;
        $inner = $this->createMockExecutor($captured);
        $detector = new EnvironmentDetector(detection: 'false', executor: ['docker', 'compose', 'exec', '-T', 'app']);
        $executor = new EnvironmentAwareExecutor($inner, $detector, 'container');

        $ctx = $this->createContext($executor);
        $ctx->exec(['composer', 'install']);

        $this->assertSame(['docker', 'compose', 'exec', '-T', 'app', 'composer', 'install'], $captured);
    }

    public function testExecDoesNotWrapInContainer(): void
    {
        $captured = null;
        $inner = $this->createMockExecutor($captured);
        $detector = new EnvironmentDetector(detection: 'true', executor: ['docker', 'compose', 'exec', '-T', 'app']);
        $executor = new EnvironmentAwareExecutor($inner, $detector, 'container');

        $ctx = $this->createContext($executor);
        $ctx->exec(['composer', 'install']);

        $this->assertSame(['composer', 'install'], $captured);
    }

    public function testExecDoesNotWrapHostTasks(): void
    {
        $captured = null;
        $inner = $this->createMockExecutor($captured);
        $detector = new EnvironmentDetector(detection: 'false', executor: ['docker', 'compose', 'exec', '-T', 'app']);
        $executor = new EnvironmentAwareExecutor($inner, $detector, 'host');

        $ctx = $this->createContext($executor);
        $ctx->exec(['docker', 'compose', 'up']);

        $this->assertSame(['docker', 'compose', 'up'], $captured);
    }

    public function testExecDoesNotWrapNullEnvironment(): void
    {
        $captured = null;
        $inner = $this->createMockExecutor($captured);
        $detector = new EnvironmentDetector(detection: 'false', executor: ['docker', 'compose', 'exec', '-T', 'app']);
        $executor = new EnvironmentAwareExecutor($inner, $detector, null);

        $ctx = $this->createContext($executor);
        $ctx->exec(['echo', 'hello']);

        $this->assertSame(['echo', 'hello'], $captured);
    }

    public function testShellWrapsWithInterpolation(): void
    {
        $captured = null;
        $inner = $this->createMockExecutor($captured);
        $detector = new EnvironmentDetector(detection: 'false', executor: ['docker', 'compose', 'exec', '-T', 'app']);
        $executor = new EnvironmentAwareExecutor($inner, $detector, 'container');

        $variables = $this->createMock(VariableResolverInterface::class);
        $variables->method('has')->willReturn(true);
        $variables->method('resolve')->willReturn('test_value');

        $ctx = $this->createContext($executor, $variables);
        $ctx->shell('echo {{ APP_ENV }}');

        // A shell string becomes the final argument of a shell inside the executor.
        $this->assertSame(['docker', 'compose', 'exec', '-T', 'app', 'sh', '-c'], \array_slice($captured, 0, 7));
        $this->assertStringStartsWith('echo ', $captured[7]);
    }

    public function testWithoutDecoratorNoWrapping(): void
    {
        $captured = null;
        $executor = $this->createMockExecutor($captured);

        $ctx = $this->createContext($executor);
        $ctx->exec(['composer', 'install']);

        $this->assertSame(['composer', 'install'], $captured);
    }

    private function createMockExecutor(array|string|null &$captured): ExecutorInterface
    {
        $executor = $this->createMock(ExecutorInterface::class);
        $executor->method('execute')
            ->willReturnCallback(static function (array|string $command) use (&$captured) {
                $captured = $command;
                $display = \is_array($command) ? implode(' ', $command) : $command;

                return new ExecutionResult(0, '', '', 0.1, $display);
            });

        return $executor;
    }

    private function createContext(
        ExecutorInterface $executor,
        ?VariableResolverInterface $variables = null,
    ): TaskContext {
        $resolver = $variables ?? $this->createMock(VariableResolverInterface::class);

        return new TaskContext(
            variables: $resolver,
            options: [],
            arguments: [],
            contextName: 'dev',
            workingDir: '/tmp',
            logger: $this->createMock(LoggerInterface::class),
            shellExecutor: $executor,
            taskRunner: $this->createMock(TaskRunnerInterface::class),
            templateRenderer: new TemplateRenderer(new TemplateParser(), $resolver),
        );
    }
}
