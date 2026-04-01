# Sputnik TaskRunner - Specification

**Version:** 1.0.0

---

## 1. Product Overview

### What is Sputnik?

Sputnik is a modern, developer-friendly CLI TaskRunner for PHP projects that combines:

- **Castor's ergonomics** - Clean DX, intuitive configuration
- **Robo's structure** - Class-based tasks, proper OOP
- **PSH's power** - Variable templating, environment management

### Key Differentiators

| Feature | Sputnik | Castor | Robo | PSH |
|---------|---------|--------|------|-----|
| Task Definition | Class + Attributes | Functions + Attributes | Class methods | YAML actions |
| Configuration | NEON | PHP | PHP/YAML | YAML |
| DI Container | Nette DI | Symfony DI | League Container | None |
| Template Engine | Simple vars only | None | None | Full expressions |
| Multi-context | First-class | Limited | Manual | Yes |

### Design Principles

1. **Explicit over implicit** - Configuration is visible and traceable
2. **Class-per-task** - Each task is a focused, testable unit
3. **No magic** - Template engine has no functions, only variable substitution
4. **Context-aware** - Environment differences are configuration, not code
5. **PHAR-first** - Designed for single-file distribution

---

## 2. Architecture

### Components

```
┌─────────────────────────────────────────────────────────────┐
│                        Sputnik CLI                          │
├─────────────────────────────────────────────────────────────┤
│  Commands    │   Task Discovery   │   Template Engine       │
│──────────────┼────────────────────┼─────────────────────────│
│              Nette DI Container                             │
│──────────────┬────────────────────┬─────────────────────────│
│  Config      │   Context          │   Variable              │
│  Loader      │   Manager          │   Resolver              │
├─────────────────────────────────────────────────────────────┤
│                    Shell Executor                            │
└─────────────────────────────────────────────────────────────┘
```

### Project Structure (using Sputnik)

```
my-project/
├── .sputnik/              # Runtime state + cache (gitignored)
├── tasks/                 # Task classes
│   ├── Database/
│   │   └── MigrateTask.php
│   └── Deploy/
│       └── ProductionTask.php
├── templates/
│   ├── src/               # Template sources
│   │   └── .env.template
│   └── dist/              # Rendered output (gitignored)
├── .sputnik.dist.neon     # Main config (committed)
├── .sputnik.neon          # Local overrides (gitignored)
└── ...
```

---

## 3. Configuration

### File Locations

| File | Purpose | Git |
|------|---------|-----|
| `.sputnik.dist.neon` | Main configuration | Committed |
| `.sputnik.neon` | Local overrides | Gitignored |
| `.sputnik/state.json` | Current context | Gitignored |

### Variable Resolution Precedence (highest first)

1. Runtime variables (`-D NAME=value` on CLI)
2. Dynamic variables (git, command, script, system, composite)
3. Context-specific constant overrides
4. Global constants
5. Sputnik defaults

### Full Configuration Example

```neon
# .sputnik.dist.neon

tasks:
    directories:
        - tasks
        - deploy

contexts:
    local:
        description: Local development
        variables:
            constants:
                debug: true
                appEnv: dev

    staging:
        description: Staging server
        variables:
            constants:
                debug: false
                appEnv: staging

    prod:
        description: Production server
        variables:
            constants:
                debug: false
                appEnv: prod

variables:
    constants:
        projectName: my-awesome-project
        phpVersion: "8.2"
        paths:
            root: %rootDir%
            src: %rootDir%/src
            var: %rootDir%/var

    dynamics:
        gitBranch:
            type: git
            property: branch

        gitCommit:
            type: git
            property: commitShort

        currentUser:
            type: command
            command: whoami

        releaseInfo:
            type: script
            script: |
                #!/bin/bash
                TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "dev")
                COMMIT=$(git rev-parse --short HEAD)
                echo "${TAG}-${COMMIT}"

        hostname:
            type: system
            property: hostname

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
        src: templates/src/.env.template
        dist: .env
        overwrite: ask

    nginx:
        src: templates/src/nginx.conf.template
        dist: var/nginx/site.conf
        overwrite: always
        contexts: [staging, prod]

defaults:
    context: local
```

### Minimal Configuration

```neon
tasks:
    directories:
        - tasks
```

### Dynamic Variable Types

| Type | Description | Key Options |
|------|-------------|-------------|
| `git` | Git repository info | `property`: branch, commit, commitShort, tag |
| `command` | Shell command output | `command`: the command to run |
| `script` | Multi-line bash script | `script`: full script content |
| `system` | System information | `property`: hostname, user, os, phpVersion, date, datetime |
| `composite` | Group of nested dynamics | `providers`: map of name → dynamic definition |

---

## 4. Task System

### Defining a Task

```php
#[Task(
    name: 'db:migrate',
    description: 'Run database migrations',
    aliases: ['migrate'],
    group: 'db',
)]
final class MigrateTask implements TaskInterface
{
    #[Option(name: 'seed', shortcut: 's', description: 'Run seeders', default: false)]
    private bool $seed;

    #[Option(name: 'step', description: 'Number of migrations', type: 'int')]
    private ?int $step;

    #[Argument(name: 'target', description: 'Target version')]
    private ?string $target;

    public function __invoke(TaskContext $ctx): TaskResult
    {
        $result = $ctx->shell('php artisan migrate --force');

        if (!$result->isSuccessful()) {
            return TaskResult::failure($result->getErrorOutput());
        }

        if ($ctx->option('seed')) {
            $ctx->shell('php artisan db:seed --force');
        }

        return TaskResult::success('Migrations completed');
    }
}
```

### Task Attributes

**`#[Task]`** on the class:
- `name` (required): Task identifier, e.g. `'db:migrate'`
- `description`: Help text
- `aliases`: Alternative names
- `group`: Grouping for `list-tasks` (derived from name if omitted: `'db:migrate'` → `'db'`)
- `hidden`: Hide from task list

**`#[Option]`** on properties:
- `name` (required): Option name (`--name`)
- `description`: Help text
- `shortcut`: Single letter (`-s`)
- `default`: Default value
- `required`: Whether required
- `type`: Type coercion (`string`, `int`, `float`, `bool`, `array`)
- `choices`: Valid values (`['dev', 'staging', 'prod']`)

**`#[Argument]`** on properties:
- `name` (required): Argument name
- `description`: Help text
- `default`: Default value (null = required)
- `isArray`: Accept multiple values

### TaskContext API

The context object passed to every task provides:

| Method | Description |
|--------|-------------|
| `get($name, $default)` | Resolve a variable |
| `option($name, $default)` | Get option value |
| `argument($name, $default)` | Get argument value |
| `shell($command, $env)` | Execute shell command with `{{ var }}` interpolation |
| `shellRaw($command, $env)` | Execute without interpolation |
| `runTask($name, $args, $opts)` | Run another task programmatically |
| `writeln($message)` | Write to console |
| `success($message)` | Write green message |
| `info/error/warning($msg)` | Log at level |
| `getContextName()` | Current context name |
| `getWorkingDir()` | Working directory |

### TaskResult

Tasks return `TaskResult::success($message)`, `TaskResult::failure($message, $exitCode)`, or `TaskResult::skipped($reason)`.

---

## 5. Template Engine

### Syntax

```
{{ variableName }}              # Variable (empty string if not defined)
{{ database.host }}             # Nested access (dot notation)
{{! requiredVar }}              # Required (fails if not defined)
{{ port | "3306" }}             # Default value
\{\{                            # Escaped literal {{
\}\}                            # Escaped literal }}
```

Templates are rendered automatically before every task execution. Context-specific templates are only rendered for matching contexts.

### Overwrite Modes

| Mode | Behavior |
|------|----------|
| `always` | Overwrite without prompting |
| `never` | Skip if file exists |
| `ask` | Prompt user (default) |

---

## 6. CLI Commands

### Command Overview

| Command | Description |
|---------|-------------|
| `sputnik run <task>` | Execute a task |
| `sputnik <task>` | Execute a task (shortcut) |
| `sputnik list-tasks` | List all available tasks |
| `sputnik init` | Initialize Sputnik in a project |
| `sputnik context:switch <name>` | Switch current context |
| `sputnik context:list` | List available contexts |

### Runtime Variables

```bash
sputnik run deploy -D ENV=staging -D DEBUG=true -D COUNT=42
```

Values are automatically coerced: `true`/`false` → bool, numeric → int/float, JSON arrays → array.

### Context Management

```bash
sputnik context:switch staging    # Persists to .sputnik/state.json
sputnik context:list              # Shows all contexts, marks current
sputnik run deploy --context=prod # One-shot override (does not persist)
```

Context resolution order: `--context` CLI flag → `SPUTNIK_CONTEXT` env var → `.sputnik/state.json` → `.sputnik.neon` → `.sputnik.dist.neon` → default `local`.

---

## 7. Event System

### Built-in Events

| Event | When | Key Properties |
|-------|------|----------------|
| `ConfigLoadedEvent` | After config loaded + container built | `$config` |
| `BeforeTaskEvent` | Before task runs (cancellable) | `$task`, `$arguments`, `$options` |
| `AfterTaskEvent` | After task completes | `$task`, `$result`, `$duration` |
| `TaskFailedEvent` | Task throws exception | `$task`, `$exception` |
| `ContextSwitchedEvent` | Context changes | `$previousContext`, `$newContext` |
| `TemplateRenderedEvent` | Template written | `$template`, `$outputPath`, `$written` |

### Defining Listeners

```php
#[AsListener(event: ContextSwitchedEvent::class, priority: -100)]
final class RegenerateTemplatesOnContextSwitch
{
    public function __construct(
        private readonly TemplateEngine $templateEngine,
    ) {}

    public function __invoke(ContextSwitchedEvent $event): void
    {
        $this->templateEngine->renderAll(overwrite: true);
    }
}
```

Listeners are discovered automatically from task directories via the `#[AsListener]` attribute.

---

## 8. PHAR Distribution

Build as single-file distributable:

```bash
php -d phar.readonly=0 vendor/bin/box compile
# Output: build/sputnik.phar (~487KB)
```

The PHAR detects its execution context automatically and resolves all paths against the current working directory (not the PHAR location).

---

## 9. Example: Full Deploy Task

```php
#[Task(
    name: 'deploy:prod',
    description: 'Deploy to production server',
    group: 'deploy',
)]
final class ProductionDeployTask implements TaskInterface
{
    #[Option(name: 'skip-backup', description: 'Skip database backup', default: false)]
    private bool $skipBackup;

    public function __invoke(TaskContext $ctx): TaskResult
    {
        if ($ctx->getContextName() !== 'prod') {
            return TaskResult::failure(
                "Production deploy requires 'prod' context. Current: {$ctx->getContextName()}"
            );
        }

        // Run prerequisites
        $testResult = $ctx->runTask('test:all');
        if (!$testResult->isSuccessful()) {
            return TaskResult::failure('Tests failed');
        }

        // Deploy
        $deployPath = $ctx->get('deployPath');
        $ctx->shell("rsync -avz --delete ./dist/ {{ deployPath }}/");
        $ctx->shell('php artisan migrate --force');
        $ctx->shell('php artisan cache:clear');

        // Health check
        $health = $ctx->shell("curl -sf https://{{ serverHost }}/health");
        if (!$health->isSuccessful()) {
            return TaskResult::failure('Health check failed');
        }

        return TaskResult::success("Deployed to {$deployPath}");
    }
}
```
