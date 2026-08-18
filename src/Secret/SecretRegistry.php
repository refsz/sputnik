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

        // Clear any previous diagnostic for this name on successful remember
        unset($this->diagnostics[$name]);

        if (!isset($this->values[$name])) {
            $this->values[$name] = [];
        }

        $this->values[$name][] = $text;

        if (\strlen($text) < self::SHORT_VALUE_LENGTH) {
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

    private function addDiagnostic(string $name, string $message): void
    {
        $this->diagnostics[$name] = $message;
    }
}
