# Configuration Reference

Sputnik is configured using [NEON](https://ne-on.org/) files located in the project root.

## Config Files

| File | Purpose |
|---|---|
| `.sputnik.dist.neon` | Base configuration, committed to VCS |
| `.sputnik.neon` | Local overrides, add to `.gitignore` |

Both files are automatically loaded and deep-merged. Nested keys are merged recursively, scalar values are replaced. Either file can exist on its own.

See [Project Structure](project-structure.md) for details on file locations and `.gitignore` recommendations.

---

## tasks

Directories to scan for task classes, relative to the project root.

```neon
tasks:
    directories:
        - dev-ops/tasks
        - src/Tasks
```

Each directory is scanned recursively for PHP classes with the `#[Task]` attribute. If a directory does not exist, it is silently skipped.

You can also register task classes explicitly:

```neon
tasks:
    classes:
        - App\Tasks\DeployTask
```

---

## contexts

Named execution environments with their own variable overrides.

```neon
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
```

Each context can override `variables.constants` (not `dynamics`). The active context is selected at runtime and its constants are deep-merged on top of the global constants. Context names are case-sensitive. See [Contexts](contexts.md).

---

## variables

Two sub-sections: `constants` and `dynamics`.

### constants

Static key-value pairs available to all tasks and templates.

```neon
variables:
    constants:
        dbHost: localhost
        dbPort: 3306
        appName: myapp
```

### dynamics

Values computed at runtime. Each dynamic variable requires a `type` field.

| Type | Description |
|---|---|
| `command` | Output of a shell command |
| `script` | Output of a multi-line shell script |
| `git` | Git repository property |
| `system` | System-level property |
| `composite` | Value assembled from other variables |

```neon
variables:
    constants:
        dbHost: localhost

    dynamics:
        gitBranch:
            type: git
            property: branch

        userId:
            type: command
            command: "id -u"

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

See [docs/variables.md](variables.md) for the full list of types and their options.

---

## templates

File templates rendered with variable substitution.

```neon
templates:
    env:
        src: .env.dist
        dist: .env
        overwrite: always
        contexts: [dev, prod]

    config:
        src: config/app.dist.php
        dist: config/app.php
        overwrite: ask
```

| Key | Required | Description |
|---|---|---|
| `src` | yes | Source template file path (relative to project root) |
| `dist` | yes | Output file path (relative to project root) |
| `overwrite` | no | `always` (default), `never`, or `ask` |
| `contexts` | no | Array of context names that use this template. `null` or omitted means all contexts |

See [docs/templates.md](templates.md).

---

## environment

Controls how Sputnik detects and executes commands inside a container or remote environment.

```neon
environment:
    detection: "test -n $CUSTOM_VAR"
    executor: "docker compose exec -T app_server {command}"
```

| Key | Required | Description |
|---|---|---|
| `detection` | no | Shell command whose exit code determines whether the current process is inside the target environment. Exit code `0` means inside. Default checks for `/.dockerenv` and `/run/.containerenv`. |
| `executor` | required for container tasks | Command template used to run tasks inside the environment. `{command}` is replaced with the actual command string. Must be present if any task uses `environment: 'container'`. |

See [docs/environments.md](environments.md).

---

## defaults

Fallback values used when no runtime state has been persisted.

```neon
defaults:
    context: dev
```

| Key | Description |
|---|---|
| `context` | Default context name used when no context has been selected and persisted |

---

## Full Example

`.sputnik.dist.neon`:

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
                appDebug: true
    staging:
        description: Staging server
        variables:
            constants:
                appEnv: staging
                appDebug: false
    prod:
        description: Production
        variables:
            constants:
                appEnv: prod
                appDebug: false

variables:
    constants:
        appName: myapp
        dbHost: localhost
        dbPort: 3306
        dbName: myapp

    dynamics:
        gitBranch:
            type: git
            property: branch

        gitCommit:
            type: git
            property: commitShort

        serverInfo:
            type: composite
            providers:
                hostname:
                    type: system
                    property: hostname
                user:
                    type: command
                    command: whoami

templates:
    env:
        src: .env.dist
        dist: .env
        overwrite: always
        contexts: [dev, staging, prod]

environment:
    detection: "test -f /.dockerenv"
    executor: "docker compose exec -T app {command}"

defaults:
    context: dev
```

`.sputnik.neon` (local overrides, gitignored):

```neon
variables:
    constants:
        dbHost: 127.0.0.1
        dbPort: 5432
        dbName: myapp_local
```
