<?php

declare(strict_types=1);

namespace Sputnik\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Argument
{
    /**
     * @param string $name        Argument name
     * @param string $description Description shown in help
     * @param mixed  $default     Default value if not provided
     * @param bool   $required    Whether argument is required
     * @param bool   $isArray     Whether argument accepts multiple values
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly mixed $default = null,
        public readonly bool $required = false,
        public readonly bool $isArray = false,
    ) {
    }
}
