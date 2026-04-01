<?php

declare(strict_types=1);

namespace Sputnik\Console;

final class ShellCallCounter
{
    public static function count(string $filePath): int
    {
        if (!file_exists($filePath)) {
            return 0;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return 0;
        }

        $tokens = token_get_all($content);
        $count = 0;
        $tokenCount = \count($tokens);

        for ($i = 0; $i < $tokenCount; ++$i) {
            // Look for ->shell( or ->shellRaw(
            if (!self::isObjectOperator($tokens[$i])) {
                continue;
            }

            // Next non-whitespace token should be 'shell' or 'shellRaw'
            $next = self::skipWhitespace($tokens, $i + 1, $tokenCount);
            if ($next === null) {
                continue;
            }

            if (!\is_array($tokens[$next])) {
                continue;
            }

            if ($tokens[$next][0] !== \T_STRING) {
                continue;
            }

            $methodName = $tokens[$next][1];
            if ($methodName !== 'shell' && $methodName !== 'shellRaw') {
                continue;
            }

            // Next non-whitespace should be '('
            $paren = self::skipWhitespace($tokens, $next + 1, $tokenCount);
            if ($paren !== null && $tokens[$paren] === '(') {
                ++$count;
            }
        }

        return $count;
    }

    private static function isObjectOperator(mixed $token): bool
    {
        return \is_array($token) && (
            $token[0] === \T_OBJECT_OPERATOR
            || $token[0] === \T_NULLSAFE_OBJECT_OPERATOR
        );
    }

    /**
     * @param list<mixed> $tokens
     */
    private static function skipWhitespace(array $tokens, int $start, int $max): ?int
    {
        for ($i = $start; $i < $max; ++$i) {
            if (\is_array($tokens[$i]) && $tokens[$i][0] === \T_WHITESPACE) {
                continue;
            }

            return $i;
        }

        return null;
    }
}
