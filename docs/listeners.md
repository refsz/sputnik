# Event Listeners

## Creating Listeners

```php
<?php
declare(strict_types=1);

use Sputnik\Attribute\AsListener;
use Sputnik\Event\ContextSwitchedEvent;

#[AsListener(event: ContextSwitchedEvent::class, priority: -50)]
final class MyListener
{
    public function __invoke(ContextSwitchedEvent $event): void
    {
        if (!$event->hasChanged()) {
            return;
        }
        // React to context change
    }
}
```

## AsListener Parameters

- `event` (required) — fully qualified event class name
- `priority` — higher runs first, default 0
- `environment` — `'container'`, `'host'`, or null. When set, an `EnvironmentAwareExecutor` is injected. The constructor must accept `ExecutorInterface $executor`.

## Environment-Aware Listeners

```php
use Sputnik\Executor\ExecutorInterface;

#[AsListener(event: ContextSwitchedEvent::class, environment: 'container')]
final class ResetOnContextSwitch
{
    public function __construct(
        private readonly ExecutorInterface $executor,
    ) {}

    public function __invoke(ContextSwitchedEvent $event): void
    {
        $this->executor->execute('composer install --no-interaction');
        // Automatically wrapped with docker exec on host, runs directly in container
    }
}
```

## Available Events

### ConfigLoadedEvent

Dispatched after configuration is loaded.

| Property | Type | Description |
|----------|------|-------------|
| `config` | `Configuration` | The loaded configuration object |

### BeforeTaskEvent

Dispatched before a task runs.

| Property/Method | Description |
|-----------------|-------------|
| `task` | The task about to run |
| `arguments` | Task arguments |
| `options` | Task options |
| `cancel(reason)` | Cancel the task with a reason string |
| `isCancelled()` | Returns true if the task has been cancelled |

### AfterTaskEvent

Dispatched after a task completes successfully.

| Property/Method | Description |
|-----------------|-------------|
| `task` | The task that ran |
| `result` | The task result |
| `duration` | Execution duration in seconds |
| `isSuccessful()` | Returns true if the task completed without error |

### TaskFailedEvent

Dispatched when a task throws an exception.

| Property | Description |
|----------|-------------|
| `task` | The task that failed |
| `exception` | The thrown exception |

### ContextSwitchedEvent

Dispatched after a context switch.

| Property/Method | Description |
|-----------------|-------------|
| `previousContext` | The context before the switch |
| `newContext` | The context after the switch |
| `hasChanged()` | Returns true if the context actually changed |

### TemplateRenderedEvent

Dispatched after a template is rendered.

| Property | Description |
|----------|-------------|
| `template` | The template that was rendered |
| `outputPath` | Path the output was written to |
| `written` | Whether the file was actually written |
| `skipReason` | Reason the file was skipped, if applicable |

## Built-in Listeners

| Listener | Priority | Description |
|----------|----------|-------------|
| `SwitchContextOnServices` | 100 | Switches VariableResolver and TemplateEngine to the new context |
| `RegenerateTemplatesOnContextSwitch` | 0 | Re-renders templates after a context switch |

## Discovery

Listeners are discovered from the same directories as tasks. A listener must:

- Have the `#[AsListener]` attribute on the class
- Implement `__invoke()` with the event as parameter
- Be placed in a directory listed in `tasks.directories`

The class name does not matter -- only the attribute determines discovery.

## Priority Order

Higher priority runs first. Use negative priorities to run after built-in listeners.

| Priority | Listener |
|----------|----------|
| 100 | `SwitchContextOnServices` |
| 0 | `RegenerateTemplatesOnContextSwitch` |
| negative | custom listeners that should run after built-ins |
