<?php

declare(strict_types=1);

namespace Sputnik\Tests\Support\Doubles;

use Sputnik\Variable\VariableResolverInterface;

final class InMemoryVariableResolver implements VariableResolverInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $variables = [];

    public function set(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function setMany(array $variables): void
    {
        foreach ($variables as $name => $value) {
            $this->set($name, $value);
        }
    }

    public function resolve(string $name, mixed $default = null): mixed
    {
        return $this->variables[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->variables);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->variables;
    }

    public function withOverrides(array $overrides): self
    {
        $clone = clone $this;
        $clone->variables = array_merge($this->variables, $overrides);

        return $clone;
    }

    public function reset(): void
    {
        $this->variables = [];
    }
}
