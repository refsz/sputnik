<?php

declare(strict_types=1);

namespace Sputnik\Secret;

use Sputnik\Template\VerbatimValueFormatter;

final class SecretRegistry
{
    private const SHORT_VALUE_LENGTH = 8;

    /**
     * @var array<string, true>
     */
    private array $names = [];

    /**
     * @var array<string, list<string>>
     */
    private array $values = [];

    /**
     * @var array<string, string>
     */
    private array $diagnostics = [];

    private readonly VerbatimValueFormatter $formatter;

    public function __construct()
    {
        $this->formatter = new VerbatimValueFormatter();
    }

    /**
     * @param list<string> $names
     */
    public function declareSecrets(array $names): void
    {
        foreach ($names as $name) {
            $this->names[$name] = true;
        }
    }

    public function isSecret(string $name): bool
    {
        return isset($this->names[$name]);
    }

    public function remember(string $name, mixed $value): void
    {
        if ($value === null) {
            $this->addDiagnostic($name, \sprintf("secret '%s' could not be resolved", $name));

            return;
        }

        $text = $this->formatter->format($value);

        if ($text === '') {
            $this->addDiagnostic(
                $name,
                \sprintf("secret '%s' resolves to an empty value and cannot be masked", $name),
            );

            return;
        }

        // Clear any previous event diagnostic for this name on successful remember
        unset($this->diagnostics[$name]);

        if (!isset($this->values[$name])) {
            $this->values[$name] = [];
        }

        // A streamed process output re-indents "\n" before the redactor ever
        // sees it, so a multi-line secret no longer occurs as that substring.
        // Accumulating each non-empty line alongside the whole value lets the
        // redactor still match it line by line.
        foreach ($this->candidatesFor($text) as $candidate) {
            // Deduplicate at write time: append only if not already present
            if (!\in_array($candidate, $this->values[$name], true)) {
                $this->values[$name][] = $candidate;
            }
        }

        // Recompute state diagnostic: set if any accumulated value is short
        if ($this->hasShortValue($name)) {
            $this->addDiagnostic(
                $name,
                \sprintf("secret '%s' has a short value; unrelated output may be masked too", $name),
            );
        }
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        $allValues = [];
        foreach ($this->values as $valueList) {
            foreach ($valueList as $value) {
                $allValues[] = $value;
            }
        }

        $unique = array_values(array_unique($allValues));

        usort($unique, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        return $unique;
    }

    /**
     * @return list<string>
     */
    public function takeDiagnostics(): array
    {
        $messages = array_values($this->diagnostics);
        $this->diagnostics = [];

        return $messages;
    }

    /**
     * @return list<string>
     */
    private function candidatesFor(string $text): array
    {
        if (!str_contains($text, "\n")) {
            return [$text];
        }

        $candidates = [$text];

        foreach (explode("\n", $text) as $line) {
            // A whitespace-only line (e.g. an indentation-only line inside a
            // multi-line secret) is not a value worth masking on its own -
            // registering it would turn every run of that whitespace in
            // unrelated output into a redaction.
            if (trim($line) !== '') {
                $candidates[] = $line;
            }
        }

        return $candidates;
    }

    private function addDiagnostic(string $name, string $message): void
    {
        $this->diagnostics[$name] = $message;
    }

    private function hasShortValue(string $name): bool
    {
        if (!isset($this->values[$name])) {
            return false;
        }

        foreach ($this->values[$name] as $value) {
            if (\strlen($value) < self::SHORT_VALUE_LENGTH) {
                return true;
            }
        }

        return false;
    }
}
