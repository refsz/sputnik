# Task Development Guide

## Basic Task

Every task implements `TaskInterface` and uses the `#[Task]` attribute.

```php
<?php
declare(strict_types=1);

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(
    name: 'db:migrate',
    description: 'Run database migrations',
    aliases: ['migrate'],
    group: 'database',
    environment: 'container',
)]
final class MigrateTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $ctx->shellRaw('vendor/bin/drush updatedb --yes');
        return TaskResult::success();
    }
}
```

## Task Attribute Parameters

| Parameter | Required | Description |
|---|---|---|
| `name` | yes | Task identifier, used as command name |
| `description` | | Shown in task list |
| `aliases` | | Alternative names, e.g. `['migrate']` allows `sputnik migrate` |
| `group` | | Grouping label in task list, e.g. `'database'`, `'docker'` |
| `hidden` | | `true` to hide from list (still executable) |
| `environment` | | `'container'`, `'host'`, or `null` (default). See [docs/environments.md](environments.md) |

## Options and Arguments

Use `#[Option]` and `#[Argument]` attributes on class properties.

```php
use Sputnik\Attribute\Argument;
use Sputnik\Attribute\Option;

#[Task(name: 'deploy')]
final class DeployTask implements TaskInterface
{
    #[Argument(name: 'target', description: 'Deploy target', required: true)]
    private string $target;

    #[Option(name: 'force', description: 'Skip confirmation', shortcut: 'f', default: false)]
    private bool $force;

    #[Option(name: 'env', description: 'Environment', type: 'string', choices: ['staging', 'production'])]
    private string $env;

    public function __invoke(TaskContext $ctx): TaskResult
    {
        $target = $ctx->argument('target');
        $force = $ctx->option('force');
        $env = $ctx->option('env');
        // ...
    }
}
```

### Option Parameters

| Parameter | Description |
|---|---|
| `name` | Required |
| `description` | |
| `shortcut` | Single letter for `-f` style |
| `default` | Default value |
| `required` | Boolean |
| `type` | `'string'`, `'int'`, `'float'`, `'bool'`, `'array'` — auto-coercion applied |
| `choices` | Restrict to specific values |

### Argument Parameters

| Parameter | Description |
|---|---|
| `name` | Required |
| `description` | |
| `default` | |
| `required` | Boolean |
| `isArray` | Accept multiple values |

## TaskContext API

### Variables

```php
$ctx->get('varName');            // resolve a variable
$ctx->get('varName', 'default'); // with fallback default
```

### Options and Arguments

```php
$ctx->option('name');   // get option value
$ctx->argument('name'); // get argument value
```

### Shell Execution

```php
$ctx->shell('echo {{ VAR }}');       // variable interpolation + escapeshellarg
$ctx->shellRaw('docker compose up'); // execute as-is
```

Both methods accept an options array:

```php
$ctx->shellRaw('make build', ['env' => ['APP_ENV' => 'prod'], 'tty' => true, 'timeout' => 60]);
```

- TTY commands automatically get no timeout.
- Returns `ExecutionResult` with: `exitCode`, `output`, `errorOutput`, `duration`, `isSuccessful()`, `assertSuccess()`.

### Sub-tasks

```php
$ctx->runTask('other:task', $arguments, $options);
```

### Output

```php
$ctx->writeln('message'); // console output with newline
$ctx->write('message');   // without newline
$ctx->success('message'); // green text
```

### Logging

```php
$ctx->info('message');
$ctx->warning('message');
$ctx->error('message');
```

### Context Info

```php
$ctx->getContextName(); // current context name
$ctx->getWorkingDir();  // project root path
```

## TaskResult

Return one of three states from `__invoke()`:

```php
return TaskResult::success('Optional message');
return TaskResult::failure('What went wrong');
return TaskResult::skipped('Why it was skipped');
```

## Runtime Variables

Users can override variables at runtime with `-D`:

```bash
sputnik deploy -D DB_HOST=remote -D DEBUG=true
```

## Task Discovery

Tasks are discovered by scanning directories listed in `tasks.directories` in the project config.

```neon
tasks:
    directories:
        - sputnik/
```

Files must:

- Be PHP files with `declare(strict_types=1)`
- Contain a class with the `#[Task]` attribute
- Implement `TaskInterface`

Namespaced classes are supported — Sputnik has a built-in classmap autoloader.
