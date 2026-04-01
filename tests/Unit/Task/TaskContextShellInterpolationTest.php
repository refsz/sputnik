<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskRunnerInterface;
use Sputnik\Tests\Support\Doubles\FakeShellExecutor;
use Sputnik\Tests\Support\Doubles\InMemoryVariableResolver;

final class TaskContextShellInterpolationTest extends TestCase
{
    private InMemoryVariableResolver $variables;
    private FakeShellExecutor $executor;
    private TaskRunnerInterface $taskRunner;
    private TaskContext $ctx;

    protected function setUp(): void
    {
        $this->variables = new InMemoryVariableResolver();
        $this->executor = new FakeShellExecutor();
        $this->taskRunner = $this->createMock(TaskRunnerInterface::class);

        $this->ctx = new TaskContext(
            variables: $this->variables,
            options: [],
            arguments: [],
            contextName: 'test',
            workingDir: sys_get_temp_dir(),
            logger: new NullLogger(),
            shellExecutor: $this->executor,
            taskRunner: $this->taskRunner,
        );
    }

    public function testStringVariableIsEscaped(): void
    {
        $this->variables->set('HOST', 'localhost');

        $this->ctx->shell('mysql -h {{ HOST }}');

        $executed = $this->executor->getExecutedCommands();
        $this->assertCount(1, $executed);
        $this->assertSame("mysql -h 'localhost'", $executed[0]['command']);
    }

    public function testMaliciousValueIsEscaped(): void
    {
        $this->variables->set('HOST', '; rm -rf /');

        $this->ctx->shell('mysql -h {{ HOST }}');

        $executed = $this->executor->getExecutedCommands();
        $this->assertCount(1, $executed);
        // The value must be wrapped in single quotes and not executed as a separate command
        $this->assertSame("mysql -h '; rm -rf /'", $executed[0]['command']);
    }

    public function testSubshellInjectionIsEscaped(): void
    {
        $this->variables->set('NAME', '$(evil_command)');

        $this->ctx->shell('echo {{ NAME }}');

        $executed = $this->executor->getExecutedCommands();
        $this->assertSame("echo '$(evil_command)'", $executed[0]['command']);
    }

    public function testBoolTrueVariableIsEscaped(): void
    {
        $this->variables->set('DEBUG', true);

        $this->ctx->shell('run --debug {{ DEBUG }}');

        $executed = $this->executor->getExecutedCommands();
        $this->assertSame("run --debug 'true'", $executed[0]['command']);
    }

    public function testBoolFalseVariableIsEscaped(): void
    {
        $this->variables->set('DEBUG', false);

        $this->ctx->shell('run --debug {{ DEBUG }}');

        $executed = $this->executor->getExecutedCommands();
        $this->assertSame("run --debug 'false'", $executed[0]['command']);
    }

    public function testArrayVariableIsJsonEncodedAndEscaped(): void
    {
        $this->variables->set('ITEMS', ['a', 'b']);

        $this->ctx->shell('process {{ ITEMS }}');

        $executed = $this->executor->getExecutedCommands();
        $this->assertSame('process \'["a","b"]\'', $executed[0]['command']);
    }

    public function testDefaultValueIsEscaped(): void
    {
        // No variable set; the default contains a space which would be dangerous unquoted
        $this->ctx->shell('connect {{ HOST | "my server" }}');

        $executed = $this->executor->getExecutedCommands();
        $this->assertSame("connect 'my server'", $executed[0]['command']);
    }

    public function testShellRawDoesNotInterpolate(): void
    {
        $this->variables->set('HOST', 'localhost');

        $this->ctx->shellRaw('mysql -h {{ HOST }}');

        $executed = $this->executor->getExecutedCommands();
        $this->assertCount(1, $executed);
        // shellRaw passes the command through unchanged
        $this->assertSame('mysql -h {{ HOST }}', $executed[0]['command']);
    }
}
