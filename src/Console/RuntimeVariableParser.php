<?php

declare(strict_types=1);

namespace Sputnik\Console;

final class RuntimeVariableParser
{
    /**
     * Parse runtime variables from -D options.
     *
     * @param array<string> $defines
     *
     * @return array<string, mixed>
     */
    public static function parse(array $defines): array
    {
        $variables = [];

        foreach ($defines as $define) {
            if (!str_contains($define, '=')) {
                // No value provided, treat as boolean true
                $variables[$define] = true;
                continue;
            }

            [$name, $value] = explode('=', $define, 2);
            $name = trim($name);

            // Try to parse JSON for complex values (arrays, objects)
            if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
                $decoded = json_decode($value, true);
                if (json_last_error() === \JSON_ERROR_NONE) {
                    $variables[$name] = $decoded;
                    continue;
                }
            }

            // Parse boolean and numeric values
            $variables[$name] = match (strtolower($value)) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value,
            };
        }

        return $variables;
    }
}
