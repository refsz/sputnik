<?php

declare(strict_types=1);

namespace Sputnik\Variable;

interface VariableResolverInterface
{
    /**
     * Resolve a variable value by name.
     *
     * @param string $name    Variable name (supports dot notation: 'database.host')
     * @param mixed  $default Default value if variable not found
     *
     * @return mixed The resolved value
     */
    public function resolve(string $name, mixed $default = null): mixed;

    /**
     * Check if a variable exists.
     */
    public function has(string $name): bool;

    /**
     * Get all resolved variables as a flat array.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Create a new resolver with runtime variable overrides.
     * Runtime variables have highest priority and override all other sources.
     *
     * @param array<string, mixed> $overrides
     */
    public function withOverrides(array $overrides): self;
}
