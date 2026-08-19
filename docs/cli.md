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

### `-V` / `--version`

Print the version and exit. A release build reports the tag and the commit it was
built from, which is what a bug report needs:

```bash
$ php sputnik.phar --version
Sputnik 0.2.0@a1b2c3d
```

### `-h` / `--help`

Show usage for the application or for a single command, including the options and
arguments a task declares:

```bash
sputnik --help
sputnik deploy --help
```

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

=== "Bash"

    ```bash
    sputnik completion bash | sudo tee /etc/bash_completion.d/sputnik > /dev/null
    ```

=== "Zsh"

    ```bash
    sputnik completion zsh | sudo tee /usr/local/share/zsh/site-functions/_sputnik > /dev/null
    ```

Restart your shell or source the completion file to activate.

## Reserved Names

### Task Names

These names carry the CLI itself and cannot be taken by a task or an alias:

`run`, `list`, `help`, `completion`, `context:switch`, `context:list`

A task that uses one is **skipped**, with a warning naming its file. Every other
task keeps working -- losing `list` would leave no way to reach them:

```
Skipped task 'list' in /project/sputnik/ListTask.php: the name is reserved by a
built-in command - rename the task or give it a group prefix
```

`init` is different: a project task may take it, and then the built-in scaffold
is no longer reachable. Scaffolding a project happens once, while a project
command called `init` may well be a daily one, so the project wins -- and says
so:

```
Task 'init' in /project/sputnik/InitTask.php shadows the built-in init command,
which is no longer reachable
```

Both warnings appear on every run, not only the one that filled the container
cache.

### Option Names

The following option names and shortcuts are reserved and cannot be used in `#[Option]` attributes:

`context`, `define`, `working-dir`, `D`
