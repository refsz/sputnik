<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Sputnik\Template\Exception\MissingVariableException;
use Sputnik\Template\TemplateParser;
use Sputnik\Template\TemplateRenderer;
use Sputnik\Tests\Support\Doubles\InMemoryVariableResolver;

final class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;
    private InMemoryVariableResolver $variables;

    protected function setUp(): void
    {
        $this->variables = new InMemoryVariableResolver();
        $this->renderer = new TemplateRenderer(
            new TemplateParser(),
            $this->variables,
        );
    }

    public function testRendersPlainText(): void
    {
        $result = $this->renderer->render('Hello, world!');

        $this->assertSame('Hello, world!', $result);
    }

    public function testRendersSimpleVariable(): void
    {
        $this->variables->set('name', 'World');

        $result = $this->renderer->render('Hello, {{ name }}!');

        $this->assertSame('Hello, World!', $result);
    }

    public function testRendersNestedVariable(): void
    {
        $this->variables->set('database.host', 'localhost');

        $result = $this->renderer->render('Host: {{ database.host }}');

        $this->assertSame('Host: localhost', $result);
    }

    public function testRendersDefaultValue(): void
    {
        $result = $this->renderer->render('Port: {{ port | "3306" }}');

        $this->assertSame('Port: 3306', $result);
    }

    public function testVariableOverridesDefault(): void
    {
        $this->variables->set('port', '5432');

        $result = $this->renderer->render('Port: {{ port | "3306" }}');

        $this->assertSame('Port: 5432', $result);
    }

    public function testThrowsOnMissingRequiredVariable(): void
    {
        $this->expectException(MissingVariableException::class);
        $this->expectExceptionMessage('apiKey');

        $this->renderer->render('Key: {{! apiKey }}');
    }

    public function testThrowsWithMultipleMissingVariables(): void
    {
        try {
            $this->renderer->render('{{! var1 }} {{! var2 }}');
            $this->fail('Expected MissingVariableException');
        } catch (MissingVariableException $e) {
            $this->assertCount(2, $e->variables);
            $this->assertContains('var1', $e->variables);
            $this->assertContains('var2', $e->variables);
        }
    }

    public function testRequiredVariableWithDefaultDoesNotThrow(): void
    {
        $result = $this->renderer->render('{{! port | "3306" }}');

        $this->assertSame('3306', $result);
    }

    public function testOptionalVariableMissingRendersEmpty(): void
    {
        $result = $this->renderer->render('Value: {{ missing }}');

        $this->assertSame('Value: ', $result);
    }

    public function testRendersBooleanTrue(): void
    {
        $this->variables->set('debug', true);

        $result = $this->renderer->render('Debug: {{ debug }}');

        $this->assertSame('Debug: true', $result);
    }

    public function testRendersBooleanFalse(): void
    {
        $this->variables->set('debug', false);

        $result = $this->renderer->render('Debug: {{ debug }}');

        $this->assertSame('Debug: false', $result);
    }

    public function testRendersInteger(): void
    {
        $this->variables->set('port', 3306);

        $result = $this->renderer->render('Port: {{ port }}');

        $this->assertSame('Port: 3306', $result);
    }

    public function testRendersFloat(): void
    {
        $this->variables->set('version', 1.5);

        $result = $this->renderer->render('Version: {{ version }}');

        $this->assertSame('Version: 1.5', $result);
    }

    public function testRendersArrayAsJson(): void
    {
        $this->variables->set('hosts', ['host1', 'host2']);

        $result = $this->renderer->render('Hosts: {{ hosts }}');

        $this->assertSame('Hosts: ["host1","host2"]', $result);
    }

    public function testTryRenderReturnsNullOnMissingRequired(): void
    {
        $result = $this->renderer->tryRender('{{! missing }}');

        $this->assertNull($result);
    }

    public function testTryRenderReturnsContentOnSuccess(): void
    {
        $this->variables->set('name', 'Test');

        $result = $this->renderer->tryRender('Hello, {{ name }}');

        $this->assertSame('Hello, Test', $result);
    }

    public function testCanRenderReturnsTrueWhenAllVariablesAvailable(): void
    {
        $this->variables->set('required', 'value');

        $result = $this->renderer->canRender('{{! required }}');

        $this->assertTrue($result);
    }

    public function testCanRenderReturnsFalseWhenMissingRequired(): void
    {
        $result = $this->renderer->canRender('{{! missing }}');

        $this->assertFalse($result);
    }

    public function testCanRenderReturnsTrueWithDefaults(): void
    {
        $result = $this->renderer->canRender('{{! port | "3306" }}');

        $this->assertTrue($result);
    }

    public function testGetMissingVariables(): void
    {
        $this->variables->set('available', 'yes');

        $missing = $this->renderer->getMissingVariables(
            '{{! available }} {{! missing1 }} {{! missing2 }}',
        );

        $this->assertCount(2, $missing);
        $this->assertContains('missing1', $missing);
        $this->assertContains('missing2', $missing);
    }

    public function testPreservesWhitespace(): void
    {
        $this->variables->set('value', 'test');

        $result = $this->renderer->render("  {{ value }}  \n");

        $this->assertSame("  test  \n", $result);
    }

    public function testHandlesEmptyTemplate(): void
    {
        $result = $this->renderer->render('');

        $this->assertSame('', $result);
    }

    public function testExceptionIncludesTemplatePath(): void
    {
        try {
            $this->renderer->render('{{! missing }}', '/path/to/template.tpl');
            $this->fail('Expected MissingVariableException');
        } catch (MissingVariableException $e) {
            $this->assertSame('/path/to/template.tpl', $e->templatePath);
            $this->assertStringContainsString('/path/to/template.tpl', $e->getMessage());
        }
    }
}
