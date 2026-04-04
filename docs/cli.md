# CLI Reference

## Global Flags

These flags are available on every command and are parsed before the application starts.

### `--context`

Override the active context for a single execution. Does not persist.

```bash
sputnik --context prod deploy
sputnik --context=staging example
```

After the command finishes, templates are re-rendered with the persisted context. The next command without `--context` runs in the previously active context.

### `--working-dir`

Change the project root directory.

```bash
sputnik --working-dir /var/www/myproject deploy
sputnik --working-dir=/var/www/myproject deploy
```

All paths (config files, task directories, templates) are resolved relative to this directory.

### `-D` / `--define`

Set runtime variables that override all other variable sources.

```bash
sputnik deploy -D DB_HOST=remote -D DEBUG=true
```

Values are automatically coerced: `true`/`false` to bool, numeric strings to int/float, JSON arrays to array. Runtime variables propagate to sub-tasks called via `$ctx->runTask()`.

Available on both direct task commands (`sputnik deploy -D ...`) and the run command (`sputnik run deploy -D ...`).

### `-v` / `--verbose`

Show additional output including log messages and stack traces on errors.

## Commands

### `init`

Initialize a new Sputnik project in the current directory.

```bash
sputnik init
sputnik init --force    # overwrite existing files
```

Creates:

- `.sputnik.dist.neon` -- project configuration
- `sputnik/ExampleTask.php` -- example task

### `run`

Run a task by name. Alternative to direct task invocation.

```bash
sputnik run deploy
sputnik run deploy -D ENV=staging
sputnik run deploy -- --force    # pass options to the task after --
```

Task options passed after `--` are parsed using the task's metadata for correct value handling.

### `context:switch`

Switch to a different context. Persists to `.sputnik/state.json`.

```bash
sputnik context:switch prod
sputnik switch prod     # alias
sputnik use prod        # alias
```

### `context:list`

List all available contexts. Current context is marked with `*`.

```bash
sputnik context:list
sputnik contexts        # alias
```

### `completion`

Generate shell completion scripts.

```bash
sputnik completion bash
sputnik completion zsh
```

## Reserved Names

### Task Names

The following names are reserved for built-in commands and cannot be used as task names or aliases:

`init`, `run`, `list`, `help`, `completion`, `context:switch`, `context:list`

### Option Names

The following option names and shortcuts are reserved and cannot be used in `#[Option]` attributes:

`context`, `define`, `working-dir`, `D`
