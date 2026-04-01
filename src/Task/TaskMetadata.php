<?php

declare(strict_types=1);

namespace Sputnik\Task;

use Sputnik\Attribute\Argument;
use Sputnik\Attribute\Option;
use Sputnik\Attribute\Task;

final class TaskMetadata
{
    /**
     * @param class-string    $className Fully qualified class name
     * @param Task            $attribute The Task attribute instance
     * @param array<Option>   $options   Option attributes from properties
     * @param array<Argument> $arguments Argument attributes from properties
     */
    public function __construct(
        public readonly string $className,
        public readonly Task $attribute,
        public readonly array $options = [],
        public readonly array $arguments = [],
    ) {
    }

    public function getName(): string
    {
        return $this->attribute->name;
    }

    public function getDescription(): string
    {
        return $this->attribute->description;
    }

    /**
     * @return array<string>
     */
    public function getAliases(): array
    {
        return $this->attribute->aliases;
    }

    public function getGroup(): ?string
    {
        return $this->attribute->group;
    }

    public function isHidden(): bool
    {
        return $this->attribute->hidden;
    }

    public function getEnvironment(): ?string
    {
        return $this->attribute->environment;
    }

    /**
     * Check if this task matches the given name or alias.
     */
    public function matches(string $nameOrAlias): bool
    {
        if ($this->attribute->name === $nameOrAlias) {
            return true;
        }

        return \in_array($nameOrAlias, $this->attribute->aliases, true);
    }
}
