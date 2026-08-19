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
     * Redact a console write()/writeln() payload: a plain string, or each
     * scalar/Stringable item of an iterable of messages. Shared by every
     * OutputInterface decorator that needs to redact before delegating,
     * so the matching rules live in exactly one place.
     *
     * @param string|iterable<mixed> $messages
     *
     * @return string|list<mixed>
     */
    public function redactMessages(string|iterable $messages): string|array
    {
        if (\is_string($messages)) {
            return $this->redact($messages);
        }

        $redacted = [];
        foreach ($messages as $message) {
            $redacted[] = match (true) {
                \is_string($message) => $this->redact($message),
                \is_scalar($message), $message instanceof \Stringable => $this->redact((string) $message),
                default => $message,
            };
        }

        return $redacted;
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
        $replaced = preg_replace($pattern, self::PLACEHOLDER, $text);

        if ($replaced === null) {
            // preg_replace() can return null under a PCRE backtrack or JIT
            // stack limit on pathological input. Masking must fail closed:
            // fall back to an unconditional substring replace rather than
            // printing the raw chunk, even though that over-masks any other
            // occurrence of the value that was not at a word boundary.
            return str_replace($value, self::PLACEHOLDER, $text);
        }

        return $replaced;
    }
}
