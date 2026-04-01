<?php

declare(strict_types=1);

namespace Sputnik\Config;

use Nette\Neon\Exception;
use Nette\Neon\Neon;
use Sputnik\Config\Exception\ConfigValidationException;

final class ConfigLoader
{
    private const BASE_CONFIG_NAME = '.sputnik.dist.neon';

    private const LOCAL_CONFIG_NAME = '.sputnik.neon';

    public function __construct(
        private readonly string $workingDir,
        private readonly bool $validate = true,
    ) {
    }

    /**
     * Load and merge configuration files.
     *
     * @throws ConfigValidationException
     */
    public function load(): Configuration
    {
        $baseConfig = $this->loadFile($this->getBasePath());
        $localConfig = $this->loadFile($this->getLocalPath());

        $merged = $this->merge($baseConfig, $localConfig);

        if ($this->validate) {
            $validator = new ConfigValidator();
            $validator->validate($merged);
        }

        return new Configuration($merged);
    }

    /**
     * Get the base config file path.
     */
    public function getBasePath(): string
    {
        return $this->workingDir . '/' . self::BASE_CONFIG_NAME;
    }

    /**
     * Get the local config file path.
     */
    public function getLocalPath(): string
    {
        return $this->workingDir . '/' . self::LOCAL_CONFIG_NAME;
    }

    /**
     * Check if a base config file exists.
     */
    public function hasConfig(): bool
    {
        return file_exists($this->getBasePath());
    }

    /**
     * Load a single NEON file.
     *
     * @return array<string, mixed>
     */
    private function loadFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        try {
            $parsed = Neon::decode($content);
        } catch (Exception $exception) {
            throw ConfigValidationException::withErrors([
                \sprintf("Failed to parse '%s': %s", $path, $exception->getMessage()),
            ]);
        }

        return \is_array($parsed) ? $parsed : [];
    }

    /**
     * Deep merge two configuration arrays.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function merge(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if (\is_array($value) && isset($result[$key]) && \is_array($result[$key])) {
                $result[$key] = $this->merge($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
