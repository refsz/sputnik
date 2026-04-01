<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Listener;

use PHPUnit\Framework\TestCase;
use Sputnik\Config\Configuration;
use Sputnik\Event\ContextSwitchedEvent;
use Sputnik\Listener\RegenerateTemplatesOnContextSwitch;
use Sputnik\Listener\SwitchContextOnServices;
use Sputnik\Template\TemplateEngine;
use Sputnik\Tests\Support\Doubles\InMemoryVariableResolver;
use Sputnik\Variable\VariableResolver;

final class ListenerTest extends TestCase
{
    // --- SwitchContextOnServices ---

    public function testSwitchContextOnServicesDoesNothingWhenContextHasNotChanged(): void
    {
        $config = new Configuration([
            'contexts' => ['local' => [], 'prod' => []],
        ]);
        $variableResolver = $this->createMock(VariableResolver::class);
        $templateEngine = $this->createMock(TemplateEngine::class);

        // Neither switchContext should be called when hasChanged() === false
        $variableResolver->expects($this->never())->method('switchContext');
        $templateEngine->expects($this->never())->method('switchContext');

        $listener = new SwitchContextOnServices($variableResolver, $templateEngine);

        // same context -> hasChanged() returns false
        $event = new ContextSwitchedEvent('local', 'local');
        $listener($event);
    }

    public function testSwitchContextOnServicesSwitchesWhenContextHasChanged(): void
    {
        $variableResolver = $this->createMock(VariableResolver::class);
        $templateEngine = $this->createMock(TemplateEngine::class);

        $variableResolver->expects($this->once())->method('switchContext')->with('prod');
        $templateEngine->expects($this->once())->method('switchContext')->with('prod');

        $listener = new SwitchContextOnServices($variableResolver, $templateEngine);

        $event = new ContextSwitchedEvent('local', 'prod');
        $listener($event);
    }

    // --- RegenerateTemplatesOnContextSwitch ---

    public function testRegenerateTemplatesDoesNothingWhenContextHasNotChanged(): void
    {
        $templateEngine = $this->createMock(TemplateEngine::class);
        $templateEngine->expects($this->never())->method('renderAll');

        $listener = new RegenerateTemplatesOnContextSwitch($templateEngine);

        $event = new ContextSwitchedEvent('local', 'local');
        $listener($event);
    }

    public function testRegenerateTemplatesCallsRenderAllWhenContextHasChanged(): void
    {
        $templateEngine = $this->createMock(TemplateEngine::class);
        $templateEngine->expects($this->once())->method('renderAll')->willReturn([]);

        $listener = new RegenerateTemplatesOnContextSwitch($templateEngine);

        $event = new ContextSwitchedEvent('local', 'prod');
        $listener($event);
    }
}
