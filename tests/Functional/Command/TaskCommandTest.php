<?php

declare(strict_types=1);

namespace Sputnik\Tests\Functional\Command;

use PHPUnit\Framework\TestCase;
use Sputnik\Attribute\Argument;
use Sputnik\Attribute\Option;
use Sputnik\Attribute\Task;
use Sputnik\Console\Command\TaskCommand;
use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskResult;
use Sputnik\Task\TaskRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class TaskCommandTest extends TestCase
{
    public function testSuccessfulTaskExecutionShowsDone(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:success'));
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('run')->willReturn(TaskResult::success('All good'));
        $runner->method('getContextName')->willReturn('dev');

        $command = new TaskCommand($metadata, $runner);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Done', $tester->getDisplay());
    }

    public function testFailedTaskExecutionReturnsFailureExitCode(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:fail'));
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('run')->willReturn(TaskResult::failure('Something broke'));
        $runner->method('getContextName')->willReturn('dev');

        $command = new TaskCommand($metadata, $runner);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testFailedTaskWithCustomExitCode(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:fail-code'));
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('run')->willReturn(TaskResult::failure('Broken', [], 42));
        $runner->method('getContextName')->willReturn('dev');

        $command = new TaskCommand($metadata, $runner);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(42, $tester->getStatusCode());
    }

    public function testSkippedTaskShowsSkippedMessage(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:skip'));
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('run')->willReturn(TaskResult::skipped('Already done'));
        $runner->method('getContextName')->willReturn('dev');

        $command = new TaskCommand($metadata, $runner);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Skipped', $tester->getDisplay());
    }

    public function testConfigureRegistersArgumentsFromMetadata(): void
    {
        $argument = new Argument(name: 'target', description: 'Deployment target', required: true);
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'deploy'), arguments: [$argument]);
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('run')->willReturn(TaskResult::success());
        $runner->method('getContextName')->willReturn('dev');

        $command = new TaskCommand($metadata, $runner);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('target'));
        $this->assertTrue($definition->getArgument('target')->isRequired());
    }

    public function testConfigureRegistersOptionalArgumentWithDefault(): void
    {
        $argument = new Argument(name: 'env', description: 'Environment', required: false, default: 'staging');
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'deploy'), arguments: [$argument]);
        $runner = $this->createMock(TaskRunner::class);

        $command = new TaskCommand($metadata, $runner);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('env'));
        $this->assertFalse($definition->getArgument('env')->isRequired());
        $this->assertSame('staging', $definition->getArgument('env')->getDefault());
    }

    public function testConfigureRegistersOptionsFromMetadata(): void
    {
        $option = new Option(name: 'force', description: 'Force deploy', shortcut: 'f', default: false);
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'deploy'), options: [$option]);
        $runner = $this->createMock(TaskRunner::class);

        $command = new TaskCommand($metadata, $runner);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('force'));
    }

    public function testConfigureRegistersOptionWithValue(): void
    {
        $option = new Option(name: 'tag', description: 'Docker image tag', required: false, default: 'latest');
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'deploy'), options: [$option]);
        $runner = $this->createMock(TaskRunner::class);

        $command = new TaskCommand($metadata, $runner);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('tag'));
        $this->assertSame('latest', $definition->getOption('tag')->getDefault());
    }

    public function testRuntimeVariablesPassedToTaskRunner(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:vars'));
        $capturedVars = [];
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('getContextName')->willReturn('dev');
        $runner->method('run')
            ->willReturnCallback(static function ($name, $args, $opts, $output, $vars) use (&$capturedVars) {
                $capturedVars = $vars;

                return TaskResult::success();
            });

        $command = new TaskCommand($metadata, $runner);
        $tester = new CommandTester($command);
        $tester->execute(['--define' => ['HOST=localhost', 'DEBUG=true']]);

        $this->assertSame('localhost', $capturedVars['HOST']);
        $this->assertTrue($capturedVars['DEBUG']);
    }

    public function testExceptionDuringExecutionShowsErrorMessage(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:boom'));
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('getContextName')->willReturn('dev');
        $runner->method('run')->willThrowException(new \RuntimeException('Kaboom!'));

        $command = new TaskCommand($metadata, $runner);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Kaboom!', $tester->getDisplay());
    }

    public function testExceptionVerboseModeShowsTrace(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'test:boom'));
        $runner = $this->createMock(TaskRunner::class);
        $runner->method('getContextName')->willReturn('dev');
        $runner->method('run')->willThrowException(new \RuntimeException('TraceMe'));

        $command = new TaskCommand($metadata, $runner);
        $tester = new CommandTester($command);
        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        // Trace string includes #0, #1, etc.
        $this->assertMatchesRegularExpression('/#\d/', $tester->getDisplay());
    }

    public function testConfigureRegistersAliasesFromMetadata(): void
    {
        $metadata = new TaskMetadata(
            'FakeTask',
            new Task(name: 'db:migrate', aliases: ['migrate', 'db:m']),
        );
        $runner = $this->createMock(TaskRunner::class);

        $command = new TaskCommand($metadata, $runner);

        $this->assertContains('migrate', $command->getAliases());
        $this->assertContains('db:m', $command->getAliases());
    }

    public function testConfigureRegistersArrayArgument(): void
    {
        $argument = new Argument(name: 'files', description: 'Files to process', required: false, isArray: true);
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'process'), arguments: [$argument]);
        $runner = $this->createMock(TaskRunner::class);

        $command = new TaskCommand($metadata, $runner);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('files'));
        $this->assertTrue($definition->getArgument('files')->isArray());
    }
}
