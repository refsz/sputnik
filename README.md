# Sputnik

A modern PHP TaskRunner with class-based tasks, environment-aware execution, and a powerful template engine.

## Features

- **Class-based tasks** with PHP 8 attributes (`#[Task]`, `#[Option]`, `#[Argument]`)
- **Environment-aware execution** — transparent command routing between host and container
- **Context system** — switch between dev/prod/staging with variable overrides
- **Template engine** — `{{ variable }}` syntax with defaults, required markers, and escape support
- **Event system** — hook into task lifecycle with `#[AsListener]`
- **Dynamic variables** — git info, shell commands, system properties, composite values
- **PHAR distribution** — single-file deployment

## Quick Start

### 1. Initialize

```bash
php sputnik.phar init
```

Creates `.sputnik.dist.neon` and a `tasks/` directory with an example task.

### 2. Create a Task

```php
<?php

declare(strict_types=1);

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(
    name: 'greet',
    description: 'Say hello',
)]
final class GreetTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $ctx->shellRaw('echo "Hello from Sputnik!"');

        return TaskResult::success();
    }
}
```

### 3. Run It

```bash
php sputnik.phar greet
```

```
🛰  Sputnik v0.1.0 │ .sputnik.dist.neon │ dev

▸ greet · Say hello

  > echo "Hello from Sputnik!"
  Hello from Sputnik!

✓ Done (0.01s)
```

## Configuration

Sputnik uses NEON format (similar to YAML). Two files:

- `.sputnik.dist.neon` — committed to VCS, shared config
- `.sputnik.neon` — local overrides, gitignored

```neon
tasks:
    directories:
        - dev-ops/tasks

contexts:
    dev:
        description: Local development
        variables:
            constants:
                appEnv: dev

    prod:
        description: Production settings
        variables:
            constants:
                appEnv: prod

variables:
    constants:
        dbHost: localhost
        dbName: myapp

    dynamics:
        gitBranch:
            type: git
            property: branch

templates:
    env:
        src: .env.dist
        dist: .env

environment:
    executor: "docker compose exec -T app {command}"

defaults:
    context: dev
```

## Documentation

- [Configuration Reference](docs/configuration.md)
- [Writing Tasks](docs/tasks.md)
- [Event Listeners](docs/listeners.md)
- [Variables](docs/variables.md)
- [Templates](docs/templates.md)
- [Contexts](docs/contexts.md)
- [Environment-Aware Execution](docs/environments.md)

## Requirements

- PHP 8.2+
- Composer (for development)

## License

MIT
