<?php

declare(strict_types=1);

namespace Sputnik\Secret;

final class SecretRedactor
{
    private const PLACEHOLDER = '***';

    private const WORD_BOUNDARY_LENGTH = 8;

    public function __construct(
        private readonly SecretRegistry $registry,
    ) {
    }

    public function redact(string $text): string
    {
        foreach ($this->registry->values() as $value) {
            foreach ($this->variantsOf($value) as $variant) {
                $text = $this->replace($text, $variant);
            }
        }

        return $text;
    }

    /**
     * The shell-escaped form only differs when the value contains a quote, in
     * which case the raw value no longer occurs in the escaped string.
     *
     * @return list<string>
     */
    private function variantsOf(string $value): array
    {
        $escaped = escapeshellarg($value);

        if (!str_contains($escaped, $value)) {
            return [$escaped, $value];
        }

        return [$value];
    }

    private function replace(string $text, string $value): string
    {
        if (\strlen($value) >= self::WORD_BOUNDARY_LENGTH) {
            return str_replace($value, self::PLACEHOLDER, $text);
        }

        $pattern = '/(?<![\w-])' . preg_quote($value, '/') . '(?![\w-])/';

        return preg_replace($pattern, self::PLACEHOLDER, $text) ?? $text;
    }
}
