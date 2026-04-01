<?php

declare(strict_types=1);

namespace Sputnik\Task;

final class OptionCoercer
{
    /**
     * @param array<string, mixed> $provided
     *
     * @return array<string, mixed>
     */
    public function resolveOptions(TaskMetadata $metadata, array $provided): array
    {
        $resolved = [];

        foreach ($metadata->options as $option) {
            $value = $provided[$option->name] ?? $option->default;

            if ($value !== null && $option->type !== null) {
                $value = $this->coerce($option->name, $value, $option->type);
            }

            if ($value !== null && $option->choices !== [] && !\in_array($value, $option->choices, true)) {
                throw new InvalidOptionException(\sprintf(
                    "Option '--%s' must be one of: %s. Got: '%s'",
                    $option->name,
                    implode(', ', array_map('strval', $option->choices)),
                    $value,
                ));
            }

            $resolved[$option->name] = $value;
        }

        foreach ($provided as $name => $value) {
            if (!isset($resolved[$name])) {
                $resolved[$name] = $value;
            }
        }

        return $resolved;
    }

    /**
     * @param array<int|string, mixed> $provided
     *
     * @return array<string, mixed>
     */
    public function resolveArguments(TaskMetadata $metadata, array $provided): array
    {
        $resolved = [];

        foreach ($metadata->arguments as $argument) {
            $resolved[$argument->name] = $provided[$argument->name] ?? $argument->default;
        }

        foreach ($provided as $name => $value) {
            $nameStr = (string) $name;
            if (!isset($resolved[$nameStr])) {
                $resolved[$nameStr] = $value;
            }
        }

        return $resolved;
    }

    private function coerce(string $name, mixed $value, string $type): mixed
    {
        return match ($type) {
            'string' => (string) $value,
            'int' => $this->coerceInt($name, $value),
            'float' => $this->coerceFloat($name, $value),
            'bool' => $this->coerceBool($name, $value),
            'array' => $this->coerceArray($name, $value),
            default => $value,
        };
    }

    private function coerceInt(string $name, mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidOptionException(\sprintf("Option '--%s' expects integer, got: '%s'", $name, $value));
    }

    private function coerceFloat(string $name, mixed $value): float
    {
        if (\is_float($value) || \is_int($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        throw new InvalidOptionException(\sprintf("Option '--%s' expects float, got: '%s'", $name, $value));
    }

    private function coerceBool(string $name, mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            return match (strtolower($value)) {
                'true', '1' => true,
                'false', '0' => false,
                default => throw new InvalidOptionException(\sprintf("Option '--%s' expects boolean, got: '%s'", $name, $value)),
            };
        }

        return (bool) $value;
    }

    /**
     * @return array<mixed>
     */
    private function coerceArray(string $name, mixed $value): array
    {
        if (\is_array($value)) {
            return $value;
        }

        if (\is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === \JSON_ERROR_NONE && \is_array($decoded)) {
                return $decoded;
            }
        }

        throw new InvalidOptionException(\sprintf("Option '--%s' expects array (JSON string), got: '%s'", $name, $value));
    }
}
