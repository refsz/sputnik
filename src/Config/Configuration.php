<?php

declare(strict_types=1);

namespace Sputnik\Config;

final class Configuration
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
    ) {
    }

    /**
     * Get a configuration value using dot notation.
     *
     * @param string $key     Key using dot notation (e.g., 'variables.constants.appEnv')
     * @param mixed  $default Default value if key not found
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Get the raw configuration array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get task namespace configurations.
     *
     * @return array<string, array{dir: string}>
     */
    public function getNamespaces(): array
    {
        return $this->get('namespaces', []);
    }

    /**
     * Get explicit task class names from configuration.
     *
     * @return list<class-string>
     */
    public function getTaskClasses(): array
    {
        return array_values($this->get('tasks.classes', []));
    }

    /**
     * Get task directories from configuration.
     *
     * Supports both:
     * - tasks.directories: array of directory paths (absolute or relative to baseDir)
     * - namespaces: keyed config with 'dir' paths
     *
     * @return array<string>
     */
    public function getTaskDirectories(string $baseDir): array
    {
        $directories = [];

        // Check for direct tasks.directories configuration
        $tasksDirs = $this->get('tasks.directories', []);
        foreach ($tasksDirs as $dir) {
            // If absolute path, use as-is; otherwise resolve relative to baseDir
            if (str_starts_with($dir, '/')) {
                if (is_dir($dir)) {
                    $directories[] = $dir;
                }
            } else {
                $fullPath = $baseDir . '/' . $dir;
                if (is_dir($fullPath)) {
                    $directories[] = $fullPath;
                }
            }
        }

        // Also check namespaces configuration
        foreach ($this->getNamespaces() as $config) {
            $dir = $config['dir'];
            $fullPath = $baseDir . '/' . $dir;
            if (is_dir($fullPath)) {
                $directories[] = $fullPath;
            }
        }

        return array_unique($directories);
    }

    /**
     * Get context configurations.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getContexts(): array
    {
        return $this->get('contexts', []);
    }

    /**
     * Get a specific context configuration.
     *
     * @return array<string, mixed>|null
     */
    public function getContext(string $name): ?array
    {
        return $this->get('contexts.' . $name);
    }

    /**
     * Get template configurations.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTemplates(): array
    {
        return $this->get('templates', []);
    }

    /**
     * Get variable configurations.
     *
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->get('variables', []);
    }

    /**
     * Get constant variables.
     *
     * @return array<string, mixed>
     */
    public function getConstants(): array
    {
        return $this->get('variables.constants', []);
    }

    /**
     * Get dynamic variable configurations.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDynamics(): array
    {
        return $this->get('variables.dynamics', []);
    }

    /**
     * Get the default context name.
     */
    public function getDefaultContext(): string
    {
        return $this->get('defaults.context', 'local');
    }
}
