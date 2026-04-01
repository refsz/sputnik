<?php

declare(strict_types=1);

namespace Sputnik\Event;

final class ListenerMetadata
{
    public function __construct(
        public readonly string $className,
        public readonly string $event,
        public readonly int $priority,
        public readonly ?string $environment = null,
    ) {
    }
}
