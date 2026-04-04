# Project Structure

## File Layout

A typical Sputnik project looks like this:

```
my-project/
├── .sputnik.dist.neon     # Main config (committed)
├── .sputnik.neon          # Local overrides (gitignored)
├── .sputnik/              # Runtime state and cache (gitignored)
│   ├── state.json         # Persisted context
│   └── cache/             # Compiled DI container
├── sputnik/               # Task and listener classes
│   ├── DeployTask.php
│   ├── BuildTask.php
│   └── MyListener.php
└── templates/             # Template source files (optional)
    └── .env.dist
```

## Configuration Files

### `.sputnik.dist.neon`

Main configuration file. Committed to version control and shared across the team.

### `.sputnik.neon`

Local overrides. Gitignored. Values are deep-merged on top of `.sputnik.dist.neon` -- nested keys are merged recursively, scalar values are replaced.

Either file can exist on its own. If both exist, they are merged. If neither exists, Sputnik starts with an empty configuration (only built-in commands available).

## Runtime Directory

### `.sputnik/`

Auto-created on first run. Contains:

- **`state.json`** -- stores the currently active context name. Updated by `context:switch`.
- **`cache/`** -- compiled Nette DI container classes. Automatically invalidated when task files change, configuration changes, or the Sputnik version changes.

## Recommended `.gitignore`

```gitignore
.sputnik/
.sputnik.neon
```

The `.sputnik/` directory is project-local state and should never be committed. The `.sputnik.neon` file contains local overrides (database credentials, paths) that differ per developer.

## Task Directories

Configured in `tasks.directories`. Scanned **recursively** for PHP files containing `#[Task]` or `#[AsListener]` attributes. If a configured directory does not exist, it is silently skipped.

Default convention is `sputnik/` (created by `sputnik init`), but any directory name works:

```neon
tasks:
    directories:
        - sputnik
        - dev-ops/tasks
        - dev-ops/listeners
```
