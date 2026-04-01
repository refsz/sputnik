<?php

declare(strict_types=1);

namespace Sputnik\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Task
{
    /**
     * @param string        $name        Task name (e.g., 'db:migrate')
     * @param string        $description Short description shown in task list
     * @param array<string> $aliases     Alternative names for the task
     * @param string|null   $group       Group for organizing tasks in list
     * @param bool          $hidden      Hide from task list (still executable)
     * @param string|null   $environment Restrict task to a specific environment (e.g., 'container', 'host')
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly array $aliases = [],
        public readonly ?string $group = null,
        public readonly bool $hidden = false,
        public readonly ?string $environment = null,
    ) {
    }
}
