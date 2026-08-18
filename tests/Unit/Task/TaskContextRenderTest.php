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

final class TaskContextRenderTest extends TestCase
{
    private InMemoryVariableResolver $variables;

    private TaskContext $ctx;

    protected function setUp(): void
    {
        $this->variables = new InMemoryVariableResolver();

        $this->ctx = new TaskContext(
            variables: $this->variables,
            options: [],
            arguments: [],
            contextName: 'test',
            workingDir: sys_get_temp_dir(),
            logger: new NullLogger(),
            shellExecutor: new FakeShellExecutor(),
            taskRunner: $this->createMock(TaskRunnerInterface::class),
            templateRenderer: new TemplateRenderer(new TemplateParser(), $this->variables),
        );
    }

    public function testVariableIsSubstituted(): void
    {
        $this->variables->set('appDomain', 'drupal.test');

        $this->assertSame(
            'name: drupal.test',
            $this->ctx->render('name: {{ appDomain }}'),
        );
    }

    public function testValueIsNotShellEscaped(): void
    {
        $this->variables->set('appName', "My App's Project");

        $this->assertSame(
            "name: My App's Project",
            $this->ctx->render('name: {{ appName }}'),
        );
    }

    public function testDefaultIsUsedForMissingVariable(): void
    {
        $this->assertSame(
            'php_version: "8.3"',
            $this->ctx->render('php_version: "{{ phpVersion | "8.3" }}"'),
        );
    }

    public function testMissingOptionalVariableRendersEmpty(): void
    {
        $this->assertSame('name: ', $this->ctx->render('name: {{ appDomain }}'));
    }

    public function testMissingRequiredVariableThrows(): void
    {
        $this->expectException(MissingVariableException::class);

        $this->ctx->render('name: {{! appDomain }}');
    }

    public function testBooleanIsStringified(): void
    {
        $this->variables->set('xdebugEnabled', false);

        $this->assertSame('xdebug: false', $this->ctx->render('xdebug: {{ xdebugEnabled }}'));
    }

    public function testEscapedBracesStayLiteral(): void
    {
        $this->assertSame(
            'echo {{ NOT_A_VARIABLE }}',
            $this->ctx->render('echo \{\{ NOT_A_VARIABLE \}\}'),
        );
    }

    public function testMultilineContentKeepsLayout(): void
    {
        $this->variables->set('appDomain', 'drupal.test');

        $template = <<<'YAML'
            name: {{ appDomain }}
            docroot: htdocs/web
            YAML;

        $expected = <<<'YAML'
            name: drupal.test
            docroot: htdocs/web
            YAML;

        $this->assertSame($expected, $this->ctx->render($template));
    }
}
