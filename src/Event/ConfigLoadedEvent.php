<?php

declare(strict_types=1);

namespace Sputnik\Event;

use Sputnik\Config\Configuration;
use Symfony\Contracts\EventDispatcher\Event;

final class ConfigLoadedEvent extends Event
{
    public function __construct(
        public readonly Configuration $config,
    ) {
    }
}
