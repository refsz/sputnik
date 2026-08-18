# Variable System

## Overview

Variables are resolved values available in tasks via `$ctx->get('name')` and in templates via `{{ name }}`.

## Resolution Priority (highest to lowest)

!!! info "Resolution Order"
    1. Runtime overrides (`-D NAME=value`)
    2. Dynamic variables
    3. Context-specific constants
    4. Global constants
    5. Built-in: `context` = current context name

## Constants

```neon
variables:
    constants:
        dbHost: localhost
        dbName: myapp
        debug: false
```

Simple key-value pairs. Can be overridden per context.

## Dynamic Variables

Computed at runtime when variables are first accessed. All dynamic variables are resolved at once and cached. If a dynamic variable fails to resolve (e.g. a command returns non-zero, or a git property is unavailable), it returns `null`.

### type: command

```neon
userId:
    type: command
    command: "id -u"
```

Executes a shell command and returns trimmed stdout.

### type: script

```neon
dockerImage:
    type: script
    script: '''
        if [ "$DEBUG" = true ]; then
            echo "dev-image"
        else
            echo "prod-image"
        fi
    '''
```

Executes a multi-line shell script. Use NEON triple-quotes (`'''`) for multi-line values.

### type: git

```neon
gitBranch:
    type: git
    property: branch
```

| Property | Description |
|----------|-------------|
| `branch` | Current branch name |
| `commit` | Full commit hash |
| `commitShort` | Short commit hash |
| `tag` | Current tag, if any |

### type: system

```neon
hostname:
    type: system
    property: hostname
```

| Property | Description |
|----------|-------------|
| `hostname` | System hostname |
| `user` | Current user |
| `os` | Operating system name |
| `phpVersion` | PHP version string |
| `cwd` | Current working directory |
| `timestamp` | Unix timestamp |
| `date` | Current date (YYYY-MM-DD) |
| `datetime` | Current date and time |

### type: composite

```neon
buildInfo:
    type: composite
    providers:
        branch:
            type: git
            property: branch
        version:
            type: command
            command: "cat VERSION"
```

Returns an associative array with the result of each named provider.

## Secrets

Variables that must never appear in output are declared under `secrets`:

```neon
variables:
    secrets:
        apiToken:
            type: command
            command: "pass show project/api"

        dbPassword:
            type: env
            name: DB_PASSWORD
```

Declaring a variable here does two things: it defines how the value is obtained,
and it marks the value as sensitive. Everything Sputnik prints — the echoed
command, streamed command output, failure messages, log lines and
`$ctx->writeln()` — has the value replaced with `***`.

Supported types are `command`, `script` and `env`. A plain scalar is a literal
value; it works, but a secret does not belong in a committed config file. `git`
and `system` are rejected here.

Secrets resolve lazily, on first access of that name, so a `pass` or `op` lookup
does not prompt for tasks that never use the secret.

`-D apiToken=xyz` overrides the value and keeps the masking.

A name declared both here and under `constants` or `dynamics` is a configuration
error.

!!! warning "What masking does not cover"
    `ExecutionResult` keeps raw output and the raw command, so a task can still
    parse them — and can still leak them with `echo` or `file_put_contents()`,
    which bypass Sputnik's output. `TaskResult` message keeps the raw failure
    message value for the same reason. Rendered template files contain the real
    value by design, and `ps aux` shows the real command line: pass a secret via
    `$ctx->shell($cmd, ['env' => [...]])` if that matters.

    An error raised before Sputnik's container is built cannot be masked, because
    no secret is known at that moment. A configuration parse error that quotes a
    line holding a literal secret is the case where that shows, which is one more
    reason to point at `pass`, `op` or the environment instead of writing a
    literal into the config file.

    Writes to a Symfony console section — `ConsoleOutput::section()` — are not
    masked. Sputnik itself never creates one, so this only matters to code that
    deliberately does.

    A secret shorter than eight characters is masked at word boundaries, so
    unrelated output may be masked too. Sputnik warns about this once per
    secret, visible with `-v`.

## Context Overrides

```neon
contexts:
    prod:
        variables:
            constants:
                debug: false
                appEnv: prod
```

Context constants override global constants when that context is active.

## Runtime Overrides

```bash
sputnik deploy -D DB_HOST=remote -D DEBUG=true
```

Highest priority. Override everything else.

## Using Variables in Tasks

```php
$dbHost = $ctx->get('dbHost');
$branch = $ctx->get('gitBranch');
$missing = $ctx->get('nonexistent', 'fallback');
```

## Using Variables in Templates

```
DB_HOST={{ dbHost }}
GIT_BRANCH={{ gitBranch }}
OPTIONAL={{ missing | "default_value" }}
```
