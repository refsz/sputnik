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
use Sputnik\Variable\VariableResolverInterface;

final class TaskContextEnvironmentTest extends TestCase
{
    public function testShellRawWrapsForContainerOnHost(): void
    {
        $captured = null;
        $inner = $this->createMockExecutor($captured);
        $detector = new EnvironmentDetector(detection: 'false', executor: 'docker compose exec -T app {command}');
        $executor = new EnvironmentAwareExecutor($inner, $detector, 'container');

        $ctx = $this->createContext($executor);
        $ctx->shellRaw('composer install');

        $this->assertSame('docker compose exec -T app composer install', $captured);
    }

    public function testShellRawDoesNotWrapInContainer(): void
    {
        $captured = null;
        $inner = $this->createMockExecutor($captured);
        $detector = new EnvironmentDetector(detection: 'true', executor: 'docker compose exec -T app {command}');
        $executor = new EnvironmentAwareExecutor($inner, $detector, 'container');

        $ctx = $this->createContext($executor);
        $ctx->shellRaw('composer install');

        $this->assertSame('composer install', $captured);
    }

    public function testShellRawDoesNotWrapHostTasks(): void
    {
        $captured = null;
        $inner = $this->createMockExecutor($captured);
        $detector = new EnvironmentDetector(detection: 'false', executor: 'docker compose exec -T app {command}');
        $executor = new EnvironmentAwareExecutor($inner, $detector, 'host');

        $ctx = $this->createContext($executor);
        $ctx->shellRaw('docker compose up');

        $this->assertSame('docker compose up', $captured);
    }

    public function testShellRawDoesNotWrapNullEnvironment(): void
    {
        $captured = null;
        $inner = $this->createMockExecutor($captured);
        $detector = new EnvironmentDetector(detection: 'false', executor: 'docker compose exec -T app {command}');
        $executor = new EnvironmentAwareExecutor($inner, $detector, null);

        $ctx = $this->createContext($executor);
        $ctx->shellRaw('echo hello');

        $this->assertSame('echo hello', $captured);
    }

    public function testShellWrapsWithInterpolation(): void
    {
        $captured = null;
        $inner = $this->createMockExecutor($captured);
        $detector = new EnvironmentDetector(detection: 'false', executor: 'docker compose exec -T app {command}');
        $executor = new EnvironmentAwareExecutor($inner, $detector, 'container');

        $variables = $this->createMock(VariableResolverInterface::class);
        $variables->method('resolve')->willReturn('test_value');

        $ctx = $this->createContext($executor, $variables);
        $ctx->shell('echo {{ APP_ENV }}');

        $this->assertStringStartsWith('docker compose exec -T app echo', $captured);
    }

    public function testWithoutDecoratorNoWrapping(): void
    {
        $captured = null;
        $executor = $this->createMockExecutor($captured);

        $ctx = $this->createContext($executor);
        $ctx->shellRaw('composer install');

        $this->assertSame('composer install', $captured);
    }

    private function createMockExecutor(?string &$captured): ExecutorInterface
    {
        $executor = $this->createMock(ExecutorInterface::class);
        $executor->method('execute')
            ->willReturnCallback(static function (string $command) use (&$captured) {
                $captured = $command;

                return new ExecutionResult(0, '', '', 0.1, $command);
            });

        return $executor;
    }

    private function createContext(
        ExecutorInterface $executor,
        ?VariableResolverInterface $variables = null,
    ): TaskContext {
        return new TaskContext(
            variables: $variables ?? $this->createMock(VariableResolverInterface::class),
            options: [],
            arguments: [],
            contextName: 'dev',
            workingDir: '/tmp',
            logger: $this->createMock(LoggerInterface::class),
            shellExecutor: $executor,
            taskRunner: $this->createMock(TaskRunnerInterface::class),
        );
    }
}
