<?php

declare(strict_types=1);

namespace Sputnik\Listener;

use Sputnik\Attribute\AsListener;
use Sputnik\Event\ContextSwitchedEvent;
use Sputnik\Template\TemplateEngine;

#[AsListener(event: ContextSwitchedEvent::class, priority: 0)]
final class RegenerateTemplatesOnContextSwitch
{
    public function __construct(
        private readonly TemplateEngine $templateEngine,
    ) {
    }

    public function __invoke(ContextSwitchedEvent $event): void
    {
        if (!$event->hasChanged()) {
            return;
        }

        // Context is already switched by SwitchContextOnServices (priority: 100)
        $this->templateEngine->renderAll();
    }
}
