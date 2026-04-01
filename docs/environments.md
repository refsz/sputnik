# Environments

## Overview

Sputnik can detect whether it runs on the host or inside a container (Docker, Podman) and transparently route commands to the right environment.

## How It Works

1. On startup, Sputnik checks for `/.dockerenv` (Docker) or `/run/.containerenv` (Podman)
2. Tasks and listeners declare their target environment via attributes
3. When a container task runs on the host, commands are automatically wrapped with the configured executor

## Configuration

```neon
environment:
    executor: "docker compose exec -T app_server {command}"
```

The `{command}` placeholder is replaced with the actual command. Examples:

- Docker Compose: `"docker compose exec -T app_server {command}"`
- DDEV: `"ddev exec {command}"`
- Podman: `"podman exec -t app {command}"`

Optional custom detection:

```neon
environment:
    detection: "test -n $MY_CONTAINER_VAR"
    executor: "docker compose exec -T app {command}"
```

## Task Environment

```php
#[Task(name: 'install', environment: 'container')]
#[Task(name: 'docker:start', environment: 'host')]
#[Task(name: 'dev:changes')]  // runs anywhere
```

## Routing Table

| Task declares | Running on | Behavior |
|--------------|-----------|----------|
| `container` | Host | Wrapped with executor |
| `container` | Container | Runs directly |
| `host` | Host | Runs directly |
| `host` | Container | Runs directly (may fail naturally) |
| null | Anywhere | Runs directly |

## Listener Environment

```php
#[AsListener(event: ContextSwitchedEvent::class, environment: 'container')]
final class MyListener
{
    public function __construct(
        private readonly ExecutorInterface $executor,
    ) {}
}
```

The injected executor automatically wraps commands when running on the host.

**Important:** The constructor parameter must be named `$executor` and typed as `ExecutorInterface`.

## Task List Display

Tasks with an environment show a tag in the task list:

```
Available tasks:
  install          Install Drupal  [container]
 docker
  docker:start     Start Docker    [host]
  docker:stop      Stop Docker     [host]
```

## Examples

**Running on host:**

```bash
sputnik install
# install task has environment: 'container'
# Commands are wrapped: docker compose exec -T app_server composer install
```

**Running inside container:**

```bash
./sputnik.phar install
# Already in container -- commands run directly: composer install
```
