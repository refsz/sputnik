<?php

declare(strict_types=1);

namespace Sputnik\Template;

final class TemplateParser
{
    private const ESC_OPEN = "\x00SPUTNIK_ESC_OPEN\x00";

    private const ESC_CLOSE = "\x00SPUTNIK_ESC_CLOSE\x00";

    /**
     * Pattern matches:
     * - {{ variable }}           - optional variable
     * - {{! variable }}          - required variable
     * - {{ variable | "default" }} - variable with default (double quotes)
     * - {{ variable | 'default' }} - variable with default (single quotes)
     */
    private const PATTERN = '/
        \{\{                           # Opening braces
        (!)?                           # Optional required marker (group 1)
        \s*                            # Optional whitespace
        ([a-zA-Z_][a-zA-Z0-9_.]*)      # Variable name with dots (group 2)
        \s*                            # Optional whitespace
        (?:
            \|                         # Pipe separator
            \s*                        # Optional whitespace
            (?:
                "([^"]*)"              # Double-quoted default (group 3)
                |
                \'([^\']*)\'           # Single-quoted default (group 4)
            )
            \s*                        # Optional whitespace
        )?
        \}\}                           # Closing braces
    /x';

    /**
     * Parse a template string into tokens.
     *
     * @return list<Token>
     */
    public function parse(string $template): array
    {
        $template = str_replace('\\{\\{', self::ESC_OPEN, $template);
        $template = str_replace('\\}\\}', self::ESC_CLOSE, $template);

        $tokens = [];
        $offset = 0;
        $line = 1;
        $lineStart = 0;

        while (preg_match(self::PATTERN, $template, $matches, \PREG_OFFSET_CAPTURE, $offset) === 1) {
            $matchStart = $matches[0][1];
            $matchLength = \strlen($matches[0][0]);

            // Add text before the match
            if ($matchStart > $offset) {
                $text = substr($template, $offset, $matchStart - $offset);
                $tokens[] = new Token(
                    type: TokenType::Text,
                    value: $text,
                    line: $line,
                    column: $offset - $lineStart + 1,
                );

                // Update line tracking
                $newlines = substr_count($text, "\n");
                if ($newlines > 0) {
                    $line += $newlines;
                    $lastNewline = strrpos($text, "\n");
                    $lineStart = $offset + ($lastNewline !== false ? $lastNewline : 0) + 1;
                }
            }

            // Determine variable type and default
            $isRequired = $matches[1][0] === '!';
            $variableName = $matches[2][0];
            $default = null;

            // Check for default value (group 3 = double quotes, group 4 = single quotes)
            // Note: We check offset !== -1 to differentiate between "not captured" and "captured empty string"
            if (isset($matches[3]) && $matches[3][1] !== -1) {
                $default = $matches[3][0];
            } elseif (isset($matches[4]) && $matches[4][1] !== -1) {
                $default = $matches[4][0];
            }

            $tokens[] = new Token(
                type: $isRequired ? TokenType::RequiredVariable : TokenType::Variable,
                value: $variableName,
                default: $default,
                line: $line,
                column: $matchStart - $lineStart + 1,
            );

            $offset = $matchStart + $matchLength;
        }

        // Add remaining text
        if ($offset < \strlen($template)) {
            $tokens[] = new Token(
                type: TokenType::Text,
                value: substr($template, $offset),
                line: $line,
                column: $offset - $lineStart + 1,
            );
        }

        foreach ($tokens as $i => $token) {
            if ($token->type === TokenType::Text) {
                $restored = str_replace(
                    [self::ESC_OPEN, self::ESC_CLOSE],
                    ['{{', '}}'],
                    $token->value,
                );
                if ($restored !== $token->value) {
                    $tokens[$i] = new Token(
                        type: $token->type,
                        value: $restored,
                        default: $token->default,
                        line: $token->line,
                        column: $token->column,
                    );
                }
            }
        }

        return $tokens;
    }

    /**
     * Extract all variable names from a template.
     *
     * @return list<string>
     */
    public function extractVariables(string $template): array
    {
        $variables = [];
        $tokens = $this->parse($template);

        foreach ($tokens as $token) {
            if ($token->isVariable()) {
                $variables[] = $token->value;
            }
        }

        return array_values(array_unique($variables));
    }

    /**
     * Extract all required variable names from a template.
     *
     * @return list<string>
     */
    public function extractRequiredVariables(string $template): array
    {
        $variables = [];
        $tokens = $this->parse($template);

        foreach ($tokens as $token) {
            if ($token->isRequired() && !$token->hasDefault()) {
                $variables[] = $token->value;
            }
        }

        return array_values(array_unique($variables));
    }
}
