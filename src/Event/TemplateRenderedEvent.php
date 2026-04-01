<?php

declare(strict_types=1);

namespace Sputnik\Event;

use Sputnik\Template\TemplateConfig;
use Symfony\Contracts\EventDispatcher\Event;

final class TemplateRenderedEvent extends Event
{
    public function __construct(
        public readonly TemplateConfig $template,
        public readonly string $outputPath,
        public readonly bool $written,
        public readonly ?string $skipReason = null,
    ) {
    }

    public function wasWritten(): bool
    {
        return $this->written;
    }

    public function wasSkipped(): bool
    {
        return !$this->written;
    }
}
