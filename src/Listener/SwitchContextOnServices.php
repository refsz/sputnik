<?php

declare(strict_types=1);

namespace Sputnik\Listener;

use Sputnik\Attribute\AsListener;
use Sputnik\Event\ContextSwitchedEvent;
use Sputnik\Template\TemplateEngine;
use Sputnik\Variable\VariableResolver;

/**
 * Core listener that updates the VariableResolver and TemplateEngine
 * when the context changes. Runs at highest priority so all other
 * listeners see the correct context.
 */
#[AsListener(event: ContextSwitchedEvent::class, priority: 100)]
final class SwitchContextOnServices
{
    public function __construct(
        private readonly VariableResolver $variableResolver,
        private readonly TemplateEngine $templateEngine,
    ) {
    }

    public function __invoke(ContextSwitchedEvent $event): void
    {
        if (!$event->hasChanged()) {
            return;
        }

        $this->variableResolver->switchContext($event->newContext);
        $this->templateEngine->switchContext($event->newContext);
    }
}
