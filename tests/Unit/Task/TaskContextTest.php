<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sputnik\Console\SputnikOutput;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskResult;
use Sputnik\Task\TaskRunnerInterface;
use Sputnik\Tests\Support\Doubles\FakeShellExecutor;
use Sputnik\Tests\Support\Doubles\InMemoryVariableResolver;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskContextTest extends TestCase
{
    private InMemoryVariableResolver $variables;
    private FakeShellExecutor $executor;
    private LoggerInterface $logger;
    private TaskRunnerInterface $taskRunner;
    private OutputInterface $output;
    private SputnikOutput $sputnikOutput;

    protected function setUp(): void
    {
        $this->variables = new InMemoryVariableResolver();
        $this->executor = new FakeShellExecutor();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->taskRunner = $this->createMock(TaskRunnerInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->sputnikOutput = $this->createMock(SputnikOutput::class);
    }

    // --- getWorkingDir ---

    public function testGetWorkingDirReturnsConstructorValue(): void
    {
        $ctx = $this->makeContext();

        $this->assertSame('/var/app', $ctx->getWorkingDir());
    }

    // --- getContextName ---

    public function testGetContextNameReturnsConstructorValue(): void
    {
        $ctx = $this->makeContext();

        $this->assertSame('staging', $ctx->getContextName());
    }

    // --- log ---

    public function testLogDelegatesToLogger(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('log')
            ->with('debug', 'hello world', ['key' => 'val']);

        $this->makeContext()->log('debug', 'hello world', ['key' => 'val']);
    }

    public function testLogWithEmptyContextArray(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('log')
            ->with('info', 'plain message', []);

        $this->makeContext()->log('info', 'plain message');
    }

    // --- info ---

    public function testInfoDelegatesToLogger(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('info message', ['a' => 1]);

        $this->makeContext()->info('info message', ['a' => 1]);
    }

    public function testInfoWithNoContext(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('bare info', []);

        $this->makeContext()->info('bare info');
    }

    // --- error ---

    public function testErrorDelegatesToLogger(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('something broke', ['code' => 500]);

        $this->makeContext()->error('something broke', ['code' => 500]);
    }

    public function testErrorWithNoContext(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('bare error', []);

        $this->makeContext()->error('bare error');
    }

    // --- warning ---

    public function testWarningDelegatesToLogger(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('watch out', ['hint' => 'check logs']);

        $this->makeContext()->warning('watch out', ['hint' => 'check logs']);
    }

    public function testWarningWithNoContext(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('bare warning', []);

        $this->makeContext()->warning('bare warning');
    }

    // --- write ---

    public function testWriteSendsToOutput(): void
    {
        $this->output
            ->expects($this->once())
            ->method('write')
            ->with('hello');

        $this->makeContext()->write('hello');
    }

    public function testWriteIsNoopWhenOutputIsNull(): void
    {
        // Should not throw — output is null, nothing to call
        $ctx = $this->makeContext(withOutput: false);
        $ctx->write('ignored');
        $this->addToAssertionCount(1);
    }

    // --- runTask ---

    public function testRunTaskDelegatesToTaskRunner(): void
    {
        $expected = TaskResult::success('done');

        $this->taskRunner
            ->expects($this->once())
            ->method('run')
            ->with('deploy:app', ['tag' => 'v1'], ['dry-run' => false], $this->output, [], $this->sputnikOutput)
            ->willReturn($expected);

        $this->sputnikOutput->method('saveSteps')->willReturn([0, 0]);
        $this->sputnikOutput->expects($this->once())->method('resetSteps');
        $this->sputnikOutput->expects($this->once())->method('restoreSteps')->with([0, 0]);

        $result = $this->makeContext()->runTask('deploy:app', ['tag' => 'v1'], ['dry-run' => false]);

        $this->assertSame($expected, $result);
    }

    public function testRunTaskSavesAndRestoresSputnikOutputSteps(): void
    {
        $savedState = [3, 5];

        $this->sputnikOutput
            ->expects($this->once())
            ->method('saveSteps')
            ->willReturn($savedState);

        $this->sputnikOutput
            ->expects($this->once())
            ->method('resetSteps');

        $this->sputnikOutput
            ->expects($this->once())
            ->method('restoreSteps')
            ->with($savedState);

        $this->taskRunner
            ->method('run')
            ->willReturn(TaskResult::success());

        $this->makeContext()->runTask('some:task');
    }

    public function testRunTaskWithNullSputnikOutputDoesNotThrow(): void
    {
        $this->taskRunner
            ->expects($this->once())
            ->method('run')
            ->willReturn(TaskResult::success());

        $ctx = new TaskContext(
            variables: $this->variables,
            options: [],
            arguments: [],
            contextName: 'test',
            workingDir: '/tmp',
            logger: $this->logger,
            shellExecutor: $this->executor,
            taskRunner: $this->taskRunner,
            output: null,
            sputnikOutput: null,
        );

        $result = $ctx->runTask('some:task');

        $this->assertTrue($result->isSuccessful());
    }

    // --- option ---

    public function testOptionReturnsValueWhenSet(): void
    {
        $ctx = $this->makeContext();

        $this->assertSame('prod', $ctx->option('env'));
        $this->assertTrue($ctx->option('verbose'));
    }

    public function testOptionReturnsDefaultWhenMissing(): void
    {
        $ctx = $this->makeContext();

        $this->assertNull($ctx->option('nonexistent'));
        $this->assertSame('fallback', $ctx->option('missing', 'fallback'));
    }

    // --- argument ---

    public function testArgumentReturnsValueWhenSet(): void
    {
        $ctx = $this->makeContext();

        $this->assertSame('main', $ctx->argument('target'));
        $this->assertSame('master', $ctx->argument('branch'));
    }

    public function testArgumentReturnsDefaultWhenMissing(): void
    {
        $ctx = $this->makeContext();

        $this->assertNull($ctx->argument('nonexistent'));
        $this->assertSame('default-val', $ctx->argument('missing', 'default-val'));
    }

    // --- getOptions / getArguments ---

    public function testGetOptionsReturnsAll(): void
    {
        $ctx = $this->makeContext();

        $this->assertSame(['verbose' => true, 'env' => 'prod'], $ctx->getOptions());
    }

    public function testGetArgumentsReturnsAll(): void
    {
        $ctx = $this->makeContext();

        $this->assertSame(['target' => 'main', 'branch' => 'master'], $ctx->getArguments());
    }

    // --- success ---

    public function testSuccessWritesToOutput(): void
    {
        $this->output
            ->expects($this->once())
            ->method('writeln')
            ->with('<info>all good</info>');

        $this->makeContext()->success('all good');
    }

    public function testSuccessIsNoopWhenOutputIsNull(): void
    {
        $ctx = $this->makeContext(withOutput: false);
        $ctx->success('ignored');
        $this->addToAssertionCount(1);
    }

    // --- writeln ---

    public function testWritelnSendsToOutput(): void
    {
        $this->output
            ->expects($this->once())
            ->method('writeln')
            ->with('line');

        $this->makeContext()->writeln('line');
    }

    public function testWritelnIsNoopWhenOutputIsNull(): void
    {
        $ctx = $this->makeContext(withOutput: false);
        $ctx->writeln('ignored');
        $this->addToAssertionCount(1);
    }

    private function makeContext(bool $withOutput = true): TaskContext
    {
        return new TaskContext(
            variables: $this->variables,
            options: ['verbose' => true, 'env' => 'prod'],
            arguments: ['target' => 'main', 'branch' => 'master'],
            contextName: 'staging',
            workingDir: '/var/app',
            logger: $this->logger,
            shellExecutor: $this->executor,
            taskRunner: $this->taskRunner,
            output: $withOutput ? $this->output : null,
            sputnikOutput: $withOutput ? $this->sputnikOutput : null,
        );
    }
}
