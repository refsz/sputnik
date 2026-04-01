<?php

declare(strict_types=1);

namespace Sputnik\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Option
{
    /**
     * @param string                  $name        Option name (used as --name)
     * @param string                  $description Description shown in help
     * @param string|null             $shortcut    Single letter shortcut (e.g., 'v' for -v)
     * @param mixed                   $default     Default value if not provided
     * @param bool                    $required    Whether option is required
     * @param string|null             $type        Type hint for validation (string, int, float, bool, array)
     * @param array<string|int|float> $choices     Valid values (empty = no restriction)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly ?string $shortcut = null,
        public readonly mixed $default = null,
        public readonly bool $required = false,
        public readonly ?string $type = null,
        public readonly array $choices = [],
    ) {
    }
}
