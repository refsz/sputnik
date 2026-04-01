<?php

declare(strict_types=1);

namespace Sputnik\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class AsListener
{
    /**
     * @param string      $event       Fully qualified event class name
     * @param int         $priority    Higher priority listeners execute first (default: 0)
     * @param string|null $environment Target environment ('container'|'host'|null). When set, an EnvironmentAwareExecutor
     *                                 is injected via DI. The listener constructor must accept a parameter named `$executor`
     *                                 typed as ExecutorInterface to receive it.
     */
    public function __construct(
        public readonly string $event,
        public readonly int $priority = 0,
        public readonly ?string $environment = null,
    ) {
    }
}
