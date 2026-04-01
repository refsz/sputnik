<?php

declare(strict_types=1);

namespace Sputnik\Variable;

use Sputnik\Config\Configuration;

final class VariableResolver implements VariableResolverInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $resolved = [];

    /**
     * @var array<string, mixed>
     */
    private array $runtimeOverrides = [];

    private bool $initialized = false;

    private readonly DynamicVariableResolver $dynamicResolver;

    public function __construct(
        private readonly Configuration $config,
        private ?string $contextName = null,
    ) {
        $this->dynamicResolver = new DynamicVariableResolver();
    }

    /**
     * Switch to a different context. Resets resolved variables.
     */
    public function switchContext(string $contextName): void
    {
        $this->contextName = $contextName;
        $this->initialized = false;
        $this->resolved = [];
    }

    /**
     * Create a new resolver with runtime variable overrides.
     * Runtime variables have highest priority and override all other sources.
     *
     * @param array<string, mixed> $overrides
     */
    public function withOverrides(array $overrides): self
    {
        $clone = clone $this;
        $clone->runtimeOverrides = array_merge($this->runtimeOverrides, $overrides);
        $clone->initialized = false;
        $clone->resolved = [];

        return $clone;
    }

    public function resolve(string $name, mixed $default = null): mixed
    {
        $this->initialize();

        return $this->getNestedValue($this->resolved, $name, $default);
    }

    public function has(string $name): bool
    {
        $this->initialize();

        return $this->hasNestedValue($this->resolved, $name);
    }

    public function all(): array
    {
        $this->initialize();

        return $this->flatten($this->resolved);
    }

    /**
     * Get all variables as a nested array.
     *
     * @return array<string, mixed>
     */
    public function allNested(): array
    {
        $this->initialize();

        return $this->resolved;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Start with constants
        $this->resolved = $this->config->getConstants();

        // Add context as a built-in variable
        if ($this->contextName !== null) {
            $this->resolved['context'] = $this->contextName;
        }

        // Apply context overrides if a context is set
        if ($this->contextName !== null) {
            $context = $this->config->getContext($this->contextName);
            if ($context !== null && isset($context['variables']['constants'])) {
                $this->resolved = $this->mergeDeep(
                    $this->resolved,
                    $context['variables']['constants'],
                );
            }
        }

        // Resolve dynamic variables
        $dynamics = $this->config->getDynamics();
        foreach ($dynamics as $name => $definition) {
            $this->resolved[$name] = $this->dynamicResolver->resolve($definition);
        }

        // Apply runtime overrides (highest priority)
        if ($this->runtimeOverrides !== []) {
            $this->resolved = $this->mergeDeep($this->resolved, $this->runtimeOverrides);
        }

        $this->initialized = true;
    }

    /**
     * @param array<string, mixed> $array
     */
    private function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $array
     */
    private function hasNestedValue(array $array, string $key): bool
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return array<string, mixed>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (\is_array($value) && $value !== [] && array_keys($value) !== range(0, \count($value) - 1)) {
                // Associative array - recurse
                $result = array_merge($result, $this->flatten($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function mergeDeep(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if (\is_array($value) && isset($result[$key]) && \is_array($result[$key])) {
                $result[$key] = $this->mergeDeep($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
