<?php

declare(strict_types=1);

namespace Sputnik\Template;

use Sputnik\Template\Exception\MissingVariableException;
use Sputnik\Variable\VariableResolverInterface;

final class TemplateRenderer
{
    public function __construct(
        private readonly TemplateParser $parser,
        private readonly VariableResolverInterface $variables,
    ) {
    }

    /**
     * Render a template string with variable substitution.
     *
     * @throws MissingVariableException If a required variable is missing
     */
    public function render(string $template, ?string $templatePath = null): string
    {
        $tokens = $this->parser->parse($template);
        $output = '';
        $missingVariables = [];

        foreach ($tokens as $token) {
            if ($token->type === TokenType::Text) {
                $output .= $token->value;
                continue;
            }

            // It's a variable token
            $value = $this->resolveVariable($token);

            if ($value === null) {
                if ($token->isRequired()) {
                    $missingVariables[] = $token->value;
                }

                // For optional variables without default, output empty string
                $output .= '';
            } else {
                $output .= $this->stringify($value);
            }
        }

        if ($missingVariables !== []) {
            throw new MissingVariableException($missingVariables, $templatePath);
        }

        return $output;
    }

    /**
     * Render a template string, returning null if any required variables are missing.
     */
    public function tryRender(string $template): ?string
    {
        try {
            return $this->render($template);
        } catch (MissingVariableException) {
            return null;
        }
    }

    /**
     * Check if a template can be rendered (all required variables are available).
     */
    public function canRender(string $template): bool
    {
        $tokens = $this->parser->parse($template);

        foreach ($tokens as $token) {
            if (!$token->isRequired()) {
                continue;
            }

            if ($token->hasDefault()) {
                continue;
            }

            if (!$this->variables->has($token->value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing required variables from a template.
     *
     * @return list<string>
     */
    public function getMissingVariables(string $template): array
    {
        $tokens = $this->parser->parse($template);
        $missing = [];

        foreach ($tokens as $token) {
            if (!$token->isRequired()) {
                continue;
            }

            if ($token->hasDefault()) {
                continue;
            }

            if (!$this->variables->has($token->value)) {
                $missing[] = $token->value;
            }
        }

        return array_values(array_unique($missing));
    }

    private function resolveVariable(Token $token): mixed
    {
        // First try to resolve from variables
        if ($this->variables->has($token->value)) {
            return $this->variables->resolve($token->value);
        }

        // Fall back to default if available
        if ($token->hasDefault()) {
            return $token->default;
        }

        return null;
    }

    private function stringify(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        if (\is_array($value)) {
            $encoded = json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

            return $encoded !== false ? $encoded : '[]';
        }

        return (string) $value;
    }
}
