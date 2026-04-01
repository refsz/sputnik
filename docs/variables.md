# Variable System

## Overview

Variables are resolved values available in tasks via `$ctx->get('name')` and in templates via `{{ name }}`.

## Resolution Priority (highest to lowest)

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

Computed at runtime when variables are first accessed. All dynamic variables are resolved at once and cached.

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
