<?php

declare(strict_types=1);

namespace Sputnik\Support;

final class PhpFileParser
{
    /**
     * Extract the fully qualified class name from a PHP file.
     */
    public static function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $namespace = null;
        $className = null;

        $tokens = token_get_all($content);
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (!\is_array($token)) {
                continue;
            }

            // Extract namespace
            if ($token[0] === \T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < $count; ++$j) {
                    if ($tokens[$j] === ';' || $tokens[$j] === '{') {
                        break;
                    }

                    if (\is_array($tokens[$j]) && \in_array($tokens[$j][0], [\T_STRING, \T_NAME_QUALIFIED], true)) {
                        $namespace .= $tokens[$j][1];
                    }
                }
            }

            // Extract class name (skip ::class which also has T_CLASS token)
            if ($token[0] === \T_CLASS || $token[0] === \T_ENUM) {
                // Check if preceded by T_DOUBLE_COLON (meaning it's ::class, not a class declaration)
                if ($i > 0 && \is_array($tokens[$i - 1]) && $tokens[$i - 1][0] === \T_DOUBLE_COLON) {
                    continue;
                }

                // Also skip whitespace before checking for T_DOUBLE_COLON
                for ($k = $i - 1; $k >= 0; --$k) {
                    if (\is_array($tokens[$k]) && $tokens[$k][0] === \T_WHITESPACE) {
                        continue;
                    }

                    if (\is_array($tokens[$k]) && $tokens[$k][0] === \T_DOUBLE_COLON) {
                        continue 2; // Skip this T_CLASS token
                    }

                    break;
                }

                for ($j = $i + 1; $j < $count; ++$j) {
                    if (\is_array($tokens[$j]) && $tokens[$j][0] === \T_STRING) {
                        $className = $tokens[$j][1];
                        break 2;
                    }
                }
            }
        }

        if ($className === null) {
            return null;
        }

        return $namespace !== null && $namespace !== '' ? $namespace . '\\' . $className : $className;
    }
}
