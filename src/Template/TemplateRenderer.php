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
        private readonly ValueFormatterInterface $formatter = new VerbatimValueFormatter(),
    ) {
    }

    /**
     * Get a renderer for the same template syntax and variables, but with
     * values formatted for a different target, e.g. escaped for a shell.
     */
    public function withFormatter(ValueFormatterInterface $formatter): self
    {
        return new self($this->parser, $this->variables, $formatter);
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

            $value = $this->resolveVariable($token);

            if ($value === null && $token->isRequired()) {
                $missingVariables[] = $token->value;
            }

            $output .= $this->formatter->format($value);
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
     *
     * Resolving is part of answering: a declared secret's definition runs,
     * exactly as it would during rendering.
     */
    public function canRender(string $template): bool
    {
        return $this->getMissingVariables($template) === [];
    }

    /**
     * Get missing required variables from a template.
     *
     * Resolving is part of answering: a declared secret's definition runs,
     * exactly as it would during rendering.
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

            if ($this->resolveVariable($token) === null) {
                $missing[] = $token->value;
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * A variable that resolves to null counts as absent, so that a null value
     * falls back to the default and a required one is reported as missing.
     */
    private function resolveVariable(Token $token): mixed
    {
        $value = $this->variables->has($token->value)
            ? $this->variables->resolve($token->value)
            : null;

        return $value ?? $token->default;
    }
}
