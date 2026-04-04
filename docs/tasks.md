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
| `environment` | | `'container'`, `'host'`, or `null` (default). See [Environments](environments.md) |

Task names must not collide with built-in commands. See [CLI Reference](cli.md#reserved-names) for the full list.

## Options and Arguments

**Arguments** are positional values -- order matters, no `--` prefix needed:

```bash
sputnik deploy production       # "production" is an argument
```

Use arguments when the value is required and its meaning is obvious from context.

**Options** are named flags with `--` prefix:

```bash
sputnik deploy --env=production --force
sputnik deploy --env production -f
```

Use options when the value is optional, has a default, or needs a name to be understandable.

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
| `name` | Required. Must not be a reserved name (`context`, `define`, `working-dir`, `D`) |
| `description` | |
| `shortcut` | Single letter for `-f` style. Must not be `D` (reserved) |
| `default` | Default value |
| `required` | Boolean |
| `type` | `'string'`, `'int'`, `'float'`, `'bool'`, `'array'` -- auto-coercion applied |
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
$ctx->option('name');       // get single option value
$ctx->argument('name');     // get single argument value
$ctx->getOptions();         // get all options as array
$ctx->getArguments();       // get all arguments as array
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

- `env` -- additional environment variables for the process
- `tty` -- allocate a TTY (disables timeout automatically)
- `timeout` -- seconds before the process is killed (default: no timeout)

Returns `ExecutionResult` with:

| Method / Property | Description |
|---|---|
| `exitCode` | Process exit code |
| `output` | Stdout content |
| `errorOutput` | Stderr content |
| `duration` | Execution time in seconds |
| `isSuccessful()` | True if exit code is 0 |
| `assertSuccess()` | Throws if exit code is not 0 |

### Sub-tasks

```php
$result = $ctx->runTask('other:task', $arguments, $options);
```

Runtime variables set with `-D` on the original command propagate to sub-tasks automatically.

### Output

```php
$ctx->writeln('message'); // console output with newline
$ctx->write('message');   // without newline
$ctx->success('message'); // green text
```

### Logging

```php
$ctx->info('message');                  // shown with -v
$ctx->warning('message');               // shown with -v
$ctx->error('message');                 // shown with -v
$ctx->log('debug', 'message', $ctx);   // generic PSR-3 log
```

Log output is only visible when running with `-v` (verbose mode).

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

Task results affect the CLI exit code: `success` and `skipped` return exit code 0, `failure` returns exit code 1 (or a custom code via the second parameter).

## Runtime Variables

Users can override variables at runtime with `-D`:

```bash
sputnik deploy -D DB_HOST=remote -D DEBUG=true
```

This works on both direct task commands and via the `run` command. Values are automatically coerced: `true`/`false` to bool, numeric strings to int/float, JSON arrays to array.

## Task Discovery

Tasks are discovered by recursively scanning directories listed in `tasks.directories` in the project config.

```neon
tasks:
    directories:
        - sputnik/
```

Files must:

- Be PHP files with `declare(strict_types=1)`
- Contain a class with the `#[Task]` attribute
- Implement `TaskInterface`

Namespaced classes are supported -- Sputnik has a built-in classmap autoloader. If a configured directory does not exist, it is silently skipped.
