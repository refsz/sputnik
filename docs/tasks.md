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
        $ctx->exec(['vendor/bin/drush', 'updatedb', '--yes']);
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

!!! warning
    A task name that collides with a built-in is skipped with a warning, except `init`, which a project task may take over. See [CLI Reference](cli.md#reserved-names).

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

### Template Strings

`render()` substitutes variables in any string, with the same syntax a template
file uses -- `{{ name }}`, `{{ name | "default" }}`, `{{ name | "" }}`.

The renderer comes from the template engine, so a string renders exactly like a
template file would: same parser, same variables. On top of that it sees the
runtime overrides of the current run (`-D` / `--define`), which template files
do not, because those are rendered before the task starts.

```php
$content = $ctx->render(file_get_contents('templates/config.yaml'));
file_put_contents('.ddev/config.yaml', $content);
```

Values are inserted verbatim, nothing is escaped -- unlike `shell()`. A variable
that does not resolve throws `MissingVariableException` and nothing is rendered;
write `{{ name | "" }}` when an empty value is what you want.

Use it when a task has to produce file content itself, for example when it wipes
a directory that configured templates were rendered into and has to put them
back within the same run. Templates declared under `templates:` are still
rendered automatically before the first task runs.

### Options and Arguments

```php
$ctx->option('name');       // get single option value
$ctx->argument('name');     // get single argument value
$ctx->getOptions();         // get all options as array
$ctx->getArguments();       // get all arguments as array
```

### Shell Execution

`exec()` runs a program with arguments and no shell. `shell()` runs a command line through a shell.

```php
$ctx->exec(['composer', 'require', '{{ package }}']);   // (1)!
$ctx->shell('drush sql-dump | gzip > dump.gz');          // (2)!
```

1. A list, so the operating system receives the arguments as they are. A value containing spaces, quotes or a semicolon is one argument and nothing else -- there is no shell to reinterpret it, which is why nothing is escaped. Placeholders are substituted per element.
2. A string, so a real shell runs it. Use it for pipes, redirects, globs and `&&`. Variables are wrapped with `escapeshellarg()` here, because a shell does read the result.

Prefer `exec()`. It is the safe default: a command that needs no shell feature
cannot be broken by one.

`shell()` uses the same template syntax as `render()` and template files, so
`{{ name | "default" }}` works in commands too and `\{\{ ... \}\}` stays literal.

A variable that does not resolve throws `MissingVariableException` and **the
command is not executed**. This matters most here: an empty substitution used to
turn `rm -rf {{ deployPath }}/` into `rm -rf /` with exit code 0. Write
`{{ name | "" }}` for an argument that may legitimately be empty -- it becomes
`''` and keeps its position in the command.

!!! warning "Commands with their own `{{ }}` syntax"
    Go templates in `docker` and `kubectl` share the delimiters. `{{.State.Running}}`
    and `{{range .items}}` are left alone because they do not look like a variable
    name, but a bare action like `{{end}}` does and is replaced. This applies to
    `exec()` too, which substitutes per element -- escape the braces:

    ```php
    $ctx->exec(['docker', 'inspect', '-f', '{{range .items}}{{.Name}}\{\{end\}\}', 'app']);
    ```

Values of variables declared under `variables.secrets` are replaced with `***`
in the echoed command and in the command's output. See
[Secrets](secrets.md) for what that does and does not cover.

Both methods accept an options array:

```php
$ctx->exec(['make', 'build'], [
    'env' => ['APP_ENV' => 'prod'],
    'tty' => true,
    'timeout' => 60,
]);
```

- `cwd` -- directory to run in (default: the project root). Prefer this over
  `shell('cd sub && ...')`, which puts the command back through a shell
- `env` -- additional environment variables for the process
- `tty` -- allocate a TTY (disables timeout automatically)
- `timeout` -- seconds before the process is killed (default: 300, five minutes). `null` means the default, not "no limit"; pass `0` or `tty: true` to remove the limit

Returns `ExecutionResult` with:

| Method / Property | Description |
|---|---|
| `exitCode` | Process exit code |
| `output` | Stdout content |
| `errorOutput` | Stderr content |
| `duration` | Execution time in seconds |
| `command` | The command as it was displayed. An argv list is joined with spaces for readability and makes no quoting promise |
| `isSuccessful()` | True if exit code is 0 |
| `assertSuccess()` | Throws if exit code is not 0 |
| `getOutput()` | Stdout, same value as the property |
| `getErrorOutput()` | Stderr, same value as the property |
| `getCombinedOutput()` | Stdout and stderr together |

### Sub-tasks

```php
$result = $ctx->runTask('other:task', $arguments, $options);
```

!!! tip
    Runtime variables set with `-D` on the original command propagate to sub-tasks automatically. No need to pass them explicitly.

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
$ctx->log('debug', 'message', ['key' => 'value']); // generic PSR-3 log
```

!!! info
    Log output is only visible when running with `-v` (verbose mode).

### Context Info

```php
$ctx->getContextName(); // current context name
$ctx->getWorkingDir();  // project root path
```

The project root is also the process working directory, so relative paths in a
task -- `file_exists('.env')`, `scandir('.ddev')` -- resolve against it, the same
place `exec()` and `shell()` run in. Use `getWorkingDir()` when you need the
absolute path, for instance to pass one to a program.

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

This works on both direct task commands and via the `run` command.

!!! tip
    Values are automatically coerced: `true`/`false` to bool, numeric strings to int/float, JSON arrays to array.

## Task Discovery

Tasks are discovered by recursively scanning directories listed in `tasks.directories` in the [project config](configuration.md).

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
