<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Sputnik\Template\TemplateParser;
use Sputnik\Template\TokenType;

final class TemplateParserTest extends TestCase
{
    private TemplateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TemplateParser();
    }

    public function testParsesPlainText(): void
    {
        $tokens = $this->parser->parse('Hello, world!');

        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::Text, $tokens[0]->type);
        $this->assertSame('Hello, world!', $tokens[0]->value);
    }

    public function testParsesSimpleVariable(): void
    {
        $tokens = $this->parser->parse('Hello, {{ name }}!');

        $this->assertCount(3, $tokens);
        $this->assertSame(TokenType::Text, $tokens[0]->type);
        $this->assertSame('Hello, ', $tokens[0]->value);

        $this->assertSame(TokenType::Variable, $tokens[1]->type);
        $this->assertSame('name', $tokens[1]->value);
        $this->assertFalse($tokens[1]->isRequired());

        $this->assertSame(TokenType::Text, $tokens[2]->type);
        $this->assertSame('!', $tokens[2]->value);
    }

    public function testParsesRequiredVariable(): void
    {
        $tokens = $this->parser->parse('Key: {{! apiKey }}');

        $this->assertCount(2, $tokens);
        $this->assertSame(TokenType::RequiredVariable, $tokens[1]->type);
        $this->assertSame('apiKey', $tokens[1]->value);
        $this->assertTrue($tokens[1]->isRequired());
    }

    public function testParsesVariableWithDoubleQuotedDefault(): void
    {
        $tokens = $this->parser->parse('Port: {{ port | "3306" }}');

        $this->assertCount(2, $tokens);
        $this->assertSame(TokenType::Variable, $tokens[1]->type);
        $this->assertSame('port', $tokens[1]->value);
        $this->assertSame('3306', $tokens[1]->default);
        $this->assertTrue($tokens[1]->hasDefault());
    }

    public function testParsesVariableWithSingleQuotedDefault(): void
    {
        $tokens = $this->parser->parse("Host: {{ host | 'localhost' }}");

        $this->assertCount(2, $tokens);
        $this->assertSame('host', $tokens[1]->value);
        $this->assertSame('localhost', $tokens[1]->default);
    }

    public function testParsesVariableWithEmptyDefault(): void
    {
        $tokens = $this->parser->parse('Value: {{ value | "" }}');

        $this->assertCount(2, $tokens);
        $this->assertSame('value', $tokens[1]->value);
        $this->assertSame('', $tokens[1]->default);
        $this->assertTrue($tokens[1]->hasDefault());
    }

    public function testParsesNestedVariableName(): void
    {
        $tokens = $this->parser->parse('{{ database.host }}');

        $this->assertCount(1, $tokens);
        $this->assertSame('database.host', $tokens[0]->value);
    }

    public function testParsesMultipleVariables(): void
    {
        $tokens = $this->parser->parse('{{ host }}:{{ port }}');

        $this->assertCount(3, $tokens);
        $this->assertSame('host', $tokens[0]->value);
        $this->assertSame(':', $tokens[1]->value);
        $this->assertSame('port', $tokens[2]->value);
    }

    public function testHandlesVariablesWithoutSpaces(): void
    {
        $tokens = $this->parser->parse('{{name}}');

        $this->assertCount(1, $tokens);
        $this->assertSame('name', $tokens[0]->value);
    }

    public function testHandlesExtraWhitespace(): void
    {
        $tokens = $this->parser->parse('{{   name   }}');

        $this->assertCount(1, $tokens);
        $this->assertSame('name', $tokens[0]->value);
    }

    public function testExtractVariables(): void
    {
        $variables = $this->parser->extractVariables(
            '{{ host }}:{{ port }} - {{ host }}',
        );

        $this->assertCount(2, $variables);
        $this->assertContains('host', $variables);
        $this->assertContains('port', $variables);
    }

    public function testExtractRequiredVariables(): void
    {
        $variables = $this->parser->extractRequiredVariables(
            '{{ optional }} {{! required }} {{ withDefault | "default" }} {{! alsoRequired }}',
        );

        $this->assertCount(2, $variables);
        $this->assertContains('required', $variables);
        $this->assertContains('alsoRequired', $variables);
    }

    public function testExtractRequiredVariablesExcludesThoseWithDefaults(): void
    {
        $variables = $this->parser->extractRequiredVariables(
            '{{! required | "hasDefault" }}',
        );

        $this->assertEmpty($variables);
    }

    public function testParsesEmptyTemplate(): void
    {
        $tokens = $this->parser->parse('');

        $this->assertEmpty($tokens);
    }

    public function testPreservesNewlines(): void
    {
        $template = "line1\n{{ var }}\nline3";
        $tokens = $this->parser->parse($template);

        $this->assertCount(3, $tokens);
        $this->assertSame("line1\n", $tokens[0]->value);
        $this->assertSame("\nline3", $tokens[2]->value);
    }

    public function testVariableNameWithUnderscores(): void
    {
        $tokens = $this->parser->parse('{{ my_variable_name }}');

        $this->assertCount(1, $tokens);
        $this->assertSame('my_variable_name', $tokens[0]->value);
    }

    public function testVariableNameWithNumbers(): void
    {
        $tokens = $this->parser->parse('{{ var2 }}');

        $this->assertCount(1, $tokens);
        $this->assertSame('var2', $tokens[0]->value);
    }

    public function testEscapedOpenBracesRenderLiteral(): void
    {
        $tokens = $this->parser->parse('Hello \\{\\{ world');
        $text = $this->tokensToText($tokens);
        $this->assertSame('Hello {{ world', $text);
    }

    public function testEscapedCloseBracesRenderLiteral(): void
    {
        $tokens = $this->parser->parse('Hello \\}\\} world');
        $text = $this->tokensToText($tokens);
        $this->assertSame('Hello }} world', $text);
    }

    public function testMixedEscapedAndUnescaped(): void
    {
        $tokens = $this->parser->parse('\\{\\{ literal \\}\\} and {{ variable }}');
        $variables = [];
        $textParts = [];
        foreach ($tokens as $token) {
            if ($token->isVariable()) {
                $variables[] = $token->value;
            } else {
                $textParts[] = $token->value;
            }
        }
        $this->assertSame(['variable'], $variables);
        $this->assertStringContainsString('{{ literal }}', implode('', $textParts));
    }

    public function testEscapedBracesNotInExtractVariables(): void
    {
        $variables = $this->parser->extractVariables('\\{\\{ not_a_var \\}\\} and {{ real_var }}');
        $this->assertSame(['real_var'], $variables);
    }

    public function testFullEscapedBlock(): void
    {
        $tokens = $this->parser->parse('\\{\\{ not a var \\}\\}');
        $text = $this->tokensToText($tokens);
        $this->assertSame('{{ not a var }}', $text);
    }

    private function tokensToText(array $tokens): string
    {
        $result = '';
        foreach ($tokens as $token) {
            $result .= $token->value;
        }

        return $result;
    }
}
