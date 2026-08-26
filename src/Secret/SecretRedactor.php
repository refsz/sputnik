<?php

declare(strict_types=1);

namespace Sputnik\Secret;

final class SecretRedactor
{
    private const PLACEHOLDER = '***';

    private const WORD_BOUNDARY_LENGTH = 8;

    /**
     * A CSI escape sequence, which is what a colouring tool emits: ESC [ then
     * parameters and a final byte. Colour is the `m` case; cursor movement and
     * the rest take the same shape.
     */
    private const ESCAPE_SEQUENCE = '\x1b\[[0-9;?]*[ -\/]*[@-~]';

    /**
     * Built patterns by value. Redaction runs per output chunk, so the same
     * value is searched for many times in one run.
     *
     * @var array<string, string>
     */
    private array $patterns = [];

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
            return $this->replaceAcrossEscapes(str_replace($value, self::PLACEHOLDER, $text), $value);
        }

        $pattern = '/(?<![\w-])' . preg_quote($value, '/') . '(?![\w-])/';
        $replaced = preg_replace($pattern, self::PLACEHOLDER, $text);

        if ($replaced === null) {
            // preg_replace() can return null under a PCRE backtrack or JIT
            // stack limit on pathological input. Masking must fail closed:
            // fall back to an unconditional substring replace rather than
            // printing the raw chunk, even though that over-masks any other
            // occurrence of the value that was not at a word boundary.
            return $this->replaceAcrossEscapes(str_replace($value, self::PLACEHOLDER, $text), $value);
        }

        return $this->replaceAcrossEscapes($replaced, $value);
    }

    /**
     * Find a value whose characters are all present but interrupted.
     *
     * A tool that colours part of a value puts an escape sequence inside it, and
     * then the value is no longer a run of characters that a search can find.
     * Sputnik announces a colour-capable terminal to the commands it runs, so
     * this is a case it creates itself.
     *
     * Only a chunk that actually carries an escape byte pays for the pattern;
     * everything else returns after one substring check.
     */
    private function replaceAcrossEscapes(string $text, string $value): string
    {
        if (!str_contains($text, "\x1b")) {
            return $text;
        }

        $replaced = preg_replace_callback(
            $this->patternFor($value),
            static function (array $match): string {
                // Keeping the sequences that were inside the value leaves the
                // terminal in the state it would have been in - dropping a reset
                // would colour everything that follows.
                preg_match_all('/' . self::ESCAPE_SEQUENCE . '/', $match[0], $codes);

                return self::PLACEHOLDER . implode('', $codes[0]);
            },
            $text,
        );

        if ($replaced === null) {
            // Fail closed, as above: drop the colour and search the plain text,
            // rather than print a value this pattern could not handle.
            $stripped = preg_replace('/' . self::ESCAPE_SEQUENCE . '/', '', $text);

            return str_replace($value, self::PLACEHOLDER, $stripped ?? $text);
        }

        return $replaced;
    }

    /**
     * The value with room for escape sequences between its characters, and the
     * same word-boundary rule a short value has without colour - the boundary is
     * measured against visible text, so leading and trailing sequences are
     * stepped over rather than counted as word characters.
     */
    private function patternFor(string $value): string
    {
        if (isset($this->patterns[$value])) {
            return $this->patterns[$value];
        }

        $gap = '(?:' . self::ESCAPE_SEQUENCE . ')*';
        $characters = str_split($value);
        $body = implode($gap, array_map(static fn (string $c): string => preg_quote($c, '/'), $characters));

        $pattern = \strlen($value) >= self::WORD_BOUNDARY_LENGTH
            ? '/' . $body . '/'
            : '/(?<![\w-])' . $gap . $body . $gap . '(?![\w-])/';

        return $this->patterns[$value] = $pattern;
    }
}
