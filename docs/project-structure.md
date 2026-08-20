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

## The Project Directory

The project is the directory holding `.sputnik.dist.neon` (or `.sputnik.neon`).
Sputnik searches for it **upwards** from where you are, the way `git` and
`composer` find their root, so a command works from anywhere inside the project:

```bash
cd htdocs/web/sites/default
sputnik deploy            # same project, same state, same place the commands run
```

Everything project-local lives there and only there: the config, the compiled
container and the persisted context. Outside a project -- no config in any parent
directory -- there is nothing to persist, and Sputnik writes nothing beside you:

```bash
cd /tmp/somewhere
sputnik --version         # leaves the directory exactly as it was
sputnik init              # this is what you came for
```

Tasks run in the project directory by default. `--working-dir` moves **that** and
nothing else: the project keeps its state where it is.

```bash
sputnik --working-dir=frontend npm:ci    # runs in frontend/, state stays at the root
```

## Runtime Directory

### `.sputnik/`

Created in the project directory on first run. Contains:

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
