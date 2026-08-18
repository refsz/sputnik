<?php

declare(strict_types=1);

namespace Sputnik\Template;

final class VerbatimValueFormatter implements ValueFormatterInterface
{
    public function format(mixed $value): string
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
