<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\Secret;

use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Sputnik\Attribute\Task;
use Sputnik\Config\Configuration;
use Sputnik\Console\SputnikOutput;
use Sputnik\Secret\RedactingOutput;
use Sputnik\Secret\SecretRedactor;
use Sputnik\Secret\SecretRegistry;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskResult;
use Sputnik\Task\TaskRunner;
use Sputnik\Template\TemplateEngine;
use Sputnik\Template\TemplateParser;
use Sputnik\Template\TemplateRenderer;
use Sputnik\Tests\Support\TestCase;
use Sputnik\Variable\VariableResolver;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SecretMaskingTest extends TestCase
{
    private const SECRET = 'ghp_abcdefghij';

    public function testCommandEchoAndStreamedOutputAreMasked(): void
    {
        $buffer = new BufferedOutput();
        $registry = new SecretRegistry();
        $output = new RedactingOutput($buffer, new SecretRedactor($registry));

        $task = new class implements TaskInterface {
            public function __invoke(TaskContext $context): TaskResult
            {
                $context->shell('echo {{! apiToken }}');

                return TaskResult::success();
            }
        };

        $this->runTask($task, $registry, $output);

        $printed = $buffer->fetch();
        $this->assertStringNotContainsString(self::SECRET, $printed);
        $this->assertStringContainsString('***', $printed);
    }

    public function testFailureIsMaskedOnPrintButRawOnTheResult(): void
    {
        $buffer = new BufferedOutput();
        $registry = new SecretRegistry();
        $output = new RedactingOutput($buffer, new SecretRedactor($registry));

        $task = new class implements TaskInterface {
            public function __invoke(TaskContext $context): TaskResult
            {
                $context->shell('false {{! apiToken }}')->assertSuccess();

                return TaskResult::success();
            }
        };

        $result = $this->runTask($task, $registry, $output);

        $this->assertFalse($result->isSuccessful());

        // Display boundary: the printed log line is masked.
        $printed = $buffer->fetch();
        $this->assertStringNotContainsString(self::SECRET, $printed);
        $this->assertStringContainsString('***', $printed);

        // Data boundary: TaskResult::message is data, not display, and keeps the
        // raw value - the same contract ExecutionResult keeps for its output.
        $this->assertStringContainsString(self::SECRET, (string) $result->message);
    }

    public function testExecutionResultKeepsTheRawValue(): void
    {
        $buffer = new BufferedOutput();
        $registry = new SecretRegistry();
        $output = new RedactingOutput($buffer, new SecretRedactor($registry));
        $seen = null;

        $task = new class($seen) implements TaskInterface {
            public function __construct(private mixed &$seen)
            {
            }

            public function __invoke(TaskContext $context): TaskResult
            {
                $this->seen = trim($context->shell('echo {{! apiToken }}')->getOutput());

                return TaskResult::success();
            }
        };

        $this->runTask($task, $registry, $output);

        // The command interpolates the secret through the shell-escaping formatter
        // (`echo 'ghp_abcdefghij'`); the shell that runs it strips the quotes, so the
        // raw stdout captured in ExecutionResult is the bare value, unmasked.
        $this->assertSame(self::SECRET, $seen);
    }

    public function testExecArgumentIsMaskedInOutputButRawInTheResult(): void
    {
        $buffer = new BufferedOutput();
        $registry = new SecretRegistry();
        $output = new RedactingOutput($buffer, new SecretRedactor($registry));
        $seen = null;

        $task = new class($seen) implements TaskInterface {
            public function __construct(private mixed &$seen)
            {
            }

            public function __invoke(TaskContext $context): TaskResult
            {
                // No shell on this path, so the value reaches the program
                // verbatim - and must still never reach the terminal.
                $this->seen = trim($context->exec(['echo', '{{! apiToken }}'])->getOutput());

                return TaskResult::success();
            }
        };

        $this->runTask($task, $registry, $output);

        $printed = $buffer->fetch();
        $this->assertStringNotContainsString(self::SECRET, $printed);
        $this->assertStringContainsString('***', $printed);
        $this->assertSame(self::SECRET, $seen);
    }

    public function testRenderedStringKeepsTheRealValue(): void
    {
        $buffer = new BufferedOutput();
        $registry = new SecretRegistry();
        $output = new RedactingOutput($buffer, new SecretRedactor($registry));
        $rendered = null;

        $task = new class($rendered) implements TaskInterface {
            public function __construct(private mixed &$rendered)
            {
            }

            public function __invoke(TaskContext $context): TaskResult
            {
                $this->rendered = $context->render('TOKEN={{! apiToken }}');

                return TaskResult::success();
            }
        };

        $this->runTask($task, $registry, $output);

        $this->assertSame('TOKEN=' . self::SECRET, $rendered);
    }

    private function runTask(TaskInterface $task, SecretRegistry $registry, RedactingOutput $output): TaskResult
    {
        $config = new Configuration([
            'variables' => ['secrets' => ['apiToken' => self::SECRET]],
        ]);

        $metadata = new TaskMetadata($task::class, new Task(name: 'test:secret'));

        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($task);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static fn (object $event): object => $event);

        $templateEngine = $this->createMock(TemplateEngine::class);
        $templateEngine->method('getTemplatesForContext')->willReturn([]);
        $templateEngine->method('rendererFor')->willReturnCallback(
            static fn (VariableResolver $variables): TemplateRenderer => new TemplateRenderer(
                new TemplateParser(),
                $variables,
            ),
        );

        $runner = new TaskRunner(
            discovery: $discovery,
            variableResolver: new VariableResolver($config, null, sys_get_temp_dir(), $registry),
            container: $container,
            logger: new NullLogger(),
            templateEngine: $templateEngine,
            eventDispatcher: $dispatcher,
            workingDir: sys_get_temp_dir(),
            contextName: 'test',
            secrets: $registry,
        );

        return $runner->run('test:secret', output: $output, sputnikOutput: new SputnikOutput($output, '0.0.0', 'test', 'test'));
    }
}
