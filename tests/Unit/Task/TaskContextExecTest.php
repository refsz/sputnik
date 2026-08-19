<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskRunnerInterface;
use Sputnik\Template\Exception\MissingVariableException;
use Sputnik\Template\TemplateParser;
use Sputnik\Template\TemplateRenderer;
use Sputnik\Tests\Support\Doubles\FakeShellExecutor;
use Sputnik\Tests\Support\Doubles\InMemoryVariableResolver;

final class TaskContextExecTest extends TestCase
{
    private InMemoryVariableResolver $variables;

    private FakeShellExecutor $executor;

    private TaskContext $ctx;

    protected function setUp(): void
    {
        $this->variables = new InMemoryVariableResolver();
        $this->executor = new FakeShellExecutor();

        $this->ctx = new TaskContext(
            variables: $this->variables,
            options: [],
            arguments: [],
            contextName: 'test',
            workingDir: sys_get_temp_dir(),
            logger: new NullLogger(),
            shellExecutor: $this->executor,
            taskRunner: $this->createMock(TaskRunnerInterface::class),
            templateRenderer: new TemplateRenderer(new TemplateParser(), $this->variables),
        );
    }

    public function testArgvIsPassedThroughUnchanged(): void
    {
        $this->ctx->exec(['drush', 'cr']);

        $this->assertSame(['drush', 'cr'], $this->executor->getExecutedCommands()[0]['command']);
    }

    public function testVariableIsSubstitutedPerElement(): void
    {
        $this->variables->set('site', 'default');

        $this->ctx->exec(['drush', 'cr', '-l', '{{ site }}']);

        $this->assertSame(['drush', 'cr', '-l', 'default'], $this->executor->getExecutedCommands()[0]['command']);
    }

    public function testValueIsNotEscapedBecauseNoShellReadsIt(): void
    {
        $this->variables->set('message', "it's a value; with $(danger)");

        $this->ctx->exec(['echo', '{{ message }}']);

        // Verbatim: one argument, no quotes added, nothing escaped.
        $this->assertSame(
            ['echo', "it's a value; with $(danger)"],
            $this->executor->getExecutedCommands()[0]['command'],
        );
    }

    public function testValueWithSpacesStaysOneArgument(): void
    {
        $this->variables->set('name', 'two words');

        $this->ctx->exec(['printf', '%s', '{{ name }}']);

        $this->assertCount(3, $this->executor->getExecutedCommands()[0]['command']);
    }

    public function testElementCanCombineLiteralAndVariable(): void
    {
        $this->variables->set('site', 'default');

        $this->ctx->exec(['drush', '--uri={{ site }}']);

        $this->assertSame(['drush', '--uri=default'], $this->executor->getExecutedCommands()[0]['command']);
    }

    public function testMissingRequiredVariableThrowsAndRunsNothing(): void
    {
        try {
            $this->ctx->exec(['drush', '-l', '{{! site }}']);
            $this->fail('Expected MissingVariableException');
        } catch (MissingVariableException $e) {
            $this->assertSame(['site'], $e->variables);
        }

        $this->assertSame([], $this->executor->getExecutedCommands());
    }

    public function testEmptyArgvIsRejectedBeforeReachingTheExecutor(): void
    {
        try {
            $this->ctx->exec([]);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException) {
            $this->assertSame([], $this->executor->getExecutedCommands());
        }
    }

    public function testOptionsArePassedThrough(): void
    {
        $this->ctx->exec(['ls'], ['timeout' => 5.0, 'env' => ['FOO' => 'bar']]);

        $options = $this->executor->getExecutedCommands()[0]['options'];

        $this->assertSame(5.0, $options['timeout']);
        $this->assertSame(['FOO' => 'bar'], $options['env']);
    }
}
