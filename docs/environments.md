# Environments

## Overview

Sputnik can detect whether it runs on the host or inside a container (Docker, Podman) and transparently route commands to the right environment.

## How It Works

1. On startup, Sputnik checks for `/.dockerenv` (Docker) or `/run/.containerenv` (Podman)
2. Tasks and listeners declare their target environment via attributes
3. When a container task runs on the host, commands are automatically wrapped with the configured executor

You can override the detection with a custom shell command. Exit code `0` means "inside the container":

```neon
environment:
    detection: "test -n $MY_CONTAINER_VAR"
    executor: "docker compose exec -T app {command}"
```

## Configuration

```neon
environment:
    executor: "docker compose exec -T app_server {command}"
```

The `{command}` placeholder is replaced literally with the actual command string. Examples:

- Docker Compose: `"docker compose exec -T app_server {command}"`
- DDEV: `"ddev exec {command}"`
- Podman: `"podman exec -t app {command}"`

The `executor` is only required if you have tasks with `environment: 'container'`. If you only use `environment: 'host'` or no environment at all, you can omit it.

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
| `host` | Container | **Error** -- host tasks cannot run inside a container |
| `container` | Host (no executor) | **Error** -- executor configuration required |
| null | Anywhere | Runs directly |

## Error Cases

### Host task inside a container

If a task declares `environment: 'host'` and Sputnik detects it is running inside a container, the task will fail with:

```
Error: Host task cannot be executed inside a container
```

There is no mechanism to execute commands on the host from inside a container. If you need both host and container commands, split them into separate tasks.

### Container task without executor

If a task declares `environment: 'container'` but no `environment.executor` is configured, the task will fail with:

```
Error: Container task requires an environment.executor in the configuration
```

Add the executor to your configuration:

```neon
environment:
    executor: "docker compose exec -T app {command}"
```

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

The injected executor automatically wraps commands when running on the host. The same routing rules and error cases apply as for tasks.

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
