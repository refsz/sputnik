<?php

declare(strict_types=1);

namespace Sputnik\Tests\Functional\Command;

use PHPUnit\Framework\TestCase;
use Sputnik\Attribute\Task;
use Sputnik\Console\Command\RunCommand;
use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskResult;
use Sputnik\Task\TaskRunner;
use Symfony\Component\Console\Tester\CommandTester;

final class RunCommandTest extends TestCase
{
    public function testRunsTaskSuccessfully(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:hello'));
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $runner = $this->createMock(TaskRunner::class);
        $runner->method('run')->willReturn(TaskResult::success('done'));
        $runner->method('getContextName')->willReturn('dev');

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'test:hello']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Done', $tester->getDisplay());
    }

    public function testUnknownTaskShowsError(): void
    {
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn(null);
        $discovery->method('getTaskNames')->willReturn([]);

        $runner = $this->createMock(TaskRunner::class);

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'nonexistent']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testRuntimeVariablesParsed(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:vars'));
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $capturedVars = [];
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('getContextName')->willReturn('dev');
        $runner->method('run')
            ->willReturnCallback(static function ($name, $args, $opts, $output, $vars) use (&$capturedVars) {
                $capturedVars = $vars;

                return TaskResult::success();
            });

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute([
            'task' => 'test:vars',
            '--define' => ['HOST=localhost', 'DEBUG=true', 'COUNT=42', 'RATE=3.14', 'DATA=["a","b"]'],
        ]);

        $this->assertSame('localhost', $capturedVars['HOST']);
        $this->assertTrue($capturedVars['DEBUG']);
        $this->assertSame(42, $capturedVars['COUNT']);
        $this->assertSame(3.14, $capturedVars['RATE']);
        $this->assertSame(['a', 'b'], $capturedVars['DATA']);
    }

    public function testSkippedTaskResultShowsSkipped(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:skip'));
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $runner = $this->createMock(TaskRunner::class);
        $runner->method('run')->willReturn(TaskResult::skipped('Already done'));
        $runner->method('getContextName')->willReturn('dev');

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'test:skip']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Skipped', $tester->getDisplay());
    }

    public function testFailedTaskResultReturnsCorrectExitCode(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:fail'));
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $runner = $this->createMock(TaskRunner::class);
        $runner->method('run')->willReturn(TaskResult::failure('Error occurred', [], 3));
        $runner->method('getContextName')->willReturn('dev');

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'test:fail']);

        $this->assertSame(3, $tester->getStatusCode());
    }

    public function testSuggestSimilarTasksOnMisspelledName(): void
    {
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn(null);
        $discovery->method('getTaskNames')->willReturn(['db:migrate', 'db:seed', 'cache:clear']);

        $runner = $this->createMock(TaskRunner::class);

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'db:migrat']);

        $display = $tester->getDisplay();
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Did you mean', $display);
        $this->assertStringContainsString('db:migrate', $display);
    }

    public function testSuggestSimilarTasksNoSuggestionsWhenTooFar(): void
    {
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn(null);
        $discovery->method('getTaskNames')->willReturn(['completely:different', 'also:unrelated']);

        $runner = $this->createMock(TaskRunner::class);

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'xyz']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringNotContainsString('Did you mean', $tester->getDisplay());
    }

    public function testParseTaskArgsLongOptionWithValue(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:args'));
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $capturedOptions = [];
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('getContextName')->willReturn('dev');
        $runner->method('run')
            ->willReturnCallback(static function ($name, $args, $opts) use (&$capturedOptions) {
                $capturedOptions = $opts;

                return TaskResult::success();
            });

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'test:args', 'args' => ['--env=production']]);

        $this->assertSame('production', $capturedOptions['env']);
    }

    public function testParseTaskArgsShortOption(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:args'));
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $capturedOptions = [];
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('getContextName')->willReturn('dev');
        $runner->method('run')
            ->willReturnCallback(static function ($name, $args, $opts) use (&$capturedOptions) {
                $capturedOptions = $opts;

                return TaskResult::success();
            });

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'test:args', 'args' => ['-v']]);

        $this->assertTrue($capturedOptions['v']);
    }

    public function testParseTaskArgsPositionalArguments(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:args'));
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $capturedArgs = [];
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('getContextName')->willReturn('dev');
        $runner->method('run')
            ->willReturnCallback(static function ($name, $args) use (&$capturedArgs) {
                $capturedArgs = $args;

                return TaskResult::success();
            });

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'test:args', 'args' => ['production', 'us-east-1']]);

        $this->assertSame('production', $capturedArgs[0]);
        $this->assertSame('us-east-1', $capturedArgs[1]);
    }

    public function testParseTaskArgsLongFlagWithoutValue(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:args'));
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $capturedOptions = [];
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('getContextName')->willReturn('dev');
        $runner->method('run')
            ->willReturnCallback(static function ($name, $args, $opts) use (&$capturedOptions) {
                $capturedOptions = $opts;

                return TaskResult::success();
            });

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'test:args', 'args' => ['--force']]);

        $this->assertTrue($capturedOptions['force']);
    }

    public function testExceptionShowsErrorMessage(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:boom'));
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $runner = $this->createMock(TaskRunner::class);
        $runner->method('getContextName')->willReturn('dev');
        $runner->method('run')->willThrowException(new \RuntimeException('Unexpected failure'));

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['task' => 'test:boom']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unexpected failure', $tester->getDisplay());
    }

    public function testExceptionVerboseModeShowsTrace(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:boom'));
        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $runner = $this->createMock(TaskRunner::class);
        $runner->method('getContextName')->willReturn('dev');
        $runner->method('run')->willThrowException(new \RuntimeException('TraceMe'));

        $command = new RunCommand($discovery, $runner);
        $tester = new CommandTester($command);
        $tester->execute(
            ['task' => 'test:boom'],
            ['verbosity' => \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE],
        );

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertMatchesRegularExpression('/#\d/', $tester->getDisplay());
    }
}
