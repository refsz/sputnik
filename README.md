# Sputnik

[![CI](https://github.com/refsz/sputnik/actions/workflows/ci.yml/badge.svg)](https://github.com/refsz/sputnik/actions/workflows/ci.yml)
[![Latest Release](https://img.shields.io/github/v/release/refsz/sputnik)](https://github.com/refsz/sputnik/releases/latest)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](composer.json)

A modern PHP TaskRunner with class-based tasks, environment-aware execution, and a powerful template engine.

## Why Sputnik?

- **Class-per-task** -- each task is a focused, testable unit with PHP 8 attributes
- **Context-aware** -- switch between dev/staging/prod with variable overrides, not code changes
- **Template engine** -- simple `{{ variable }}` syntax, no magic functions
- **Environment-aware** -- transparent command routing between host and container
- **PHAR-first** -- single-file distribution, zero dependencies at runtime

## Installation

### PHAR (recommended)

Download the latest release:

```bash
curl -Lo sputnik.phar https://github.com/refsz/sputnik/releases/latest/download/sputnik.phar
chmod +x sputnik.phar
```

Verify the checksum:

```bash
curl -Lo sputnik.phar.sha256 https://github.com/refsz/sputnik/releases/latest/download/sputnik.phar.sha256
sha256sum -c sputnik.phar.sha256
```

### Composer

```bash
composer require --dev sputnik/sputnik
```

## Quick Start

### 1. Initialize

```bash
php sputnik.phar init
```

Creates `.sputnik.dist.neon` and a `sputnik/` directory with an example task.

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

## Configuration

Sputnik uses NEON format (similar to YAML). Two files:

- `.sputnik.dist.neon` -- committed to VCS, shared config
- `.sputnik.neon` -- local overrides, gitignored

```neon
tasks:
    directories:
        - sputnik

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

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup and guidelines.

## Security

See [SECURITY.md](SECURITY.md) for reporting vulnerabilities.

## License

MIT -- see [LICENSE](LICENSE) for details.
