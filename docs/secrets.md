# Secrets

Some variables must never reach the terminal: an API token, a database password,
a deploy key. Sputnik masks them at the display boundary. The value is resolved
and used normally -- the program that needs it receives the real thing -- but
everything Sputnik prints has it replaced with `***`.

## Declaring a secret

Secrets live under `variables.secrets`, alongside `constants` and `dynamics`:

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
and it marks the value as sensitive.

```php
$ctx->exec(['curl', '-H', 'Authorization: Bearer {{ apiToken }}', '{{ apiUrl }}']);
```

```
  > curl -H Authorization: Bearer *** https://api.example.com
```

The header curl receives holds the real token. Only the display is masked.

## What is masked

Everything that goes through Sputnik's output:

- the echoed command line, for both `exec()` and `shell()`
- streamed command output, stdout and stderr
- failure messages and log lines
- `$ctx->writeln()`, `$ctx->write()` and `$ctx->success()`
- output written by [event listeners](listeners.md)

Masking applies to the value, not to the variable name. A secret that appears in
output because a program echoed it back is masked just the same, and so is a
value that arrives by a route Sputnik never saw.

## Supported types

`command`, `script` and `env`. A plain scalar works as a literal value, but a
secret does not belong in a committed config file. `git` and `system` are
rejected here, at config load rather than at first access.

A misspelled section name is rejected too, which matters more than it sounds: an
unknown key under `variables` used to be accepted, so `secrests:` left every
secret it declared resolving to `null` - nothing was masked because no value
existed, and the task ran on with an empty argument and exited 0.

!!! note "`env` is a secret-only type"
    `variables.dynamics` does not accept `type: env` -- it allows `command`,
    `git`, `script`, `system` and `composite`. Reading a plain, non-sensitive
    environment variable is a `command` dynamic (`command: "printenv HOME"`).

Secrets resolve lazily, on first access of that name, so a `pass` or `op` lookup
does not prompt for tasks that never use the secret.

`-D apiToken=xyz` overrides the value and keeps the masking.

A name declared both here and under `constants` or `dynamics` is a configuration
error -- a secret cannot be shadowed by a source that would not be masked.

## What masking does not cover

Masking is a display filter, not an information-flow guarantee. It stops the
accidental leak -- a token in a CI log, a password in a failing command -- and
these are the edges it does not reach.

!!! warning "Boundaries"
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

    A command run with `['tty' => true]` inherits the terminal's descriptors
    directly, so its output never passes through PHP and is not masked — only
    the echoed command line is. Separately, a value split across two reads of
    the output stream is not matched, since redaction runs per chunk as it
    streams.

    A secret shorter than eight characters is masked at word boundaries, so
    unrelated output may be masked too — and, in the other direction, an
    occurrence inside a longer word is not masked at all: a four-character PIN
    inside `user_1234` survives. Sputnik warns about the short value once per
    secret, visible with `-v`.

    A multi-line value is masked line by line as well as whole, because streamed
    output is indented before it is written. That means a short line inside a
    long secret — an alias or a PIN on its own line — brings the word-boundary
    behaviour above with it, and triggers the same warning.

    A literal secret written into the config file is compiled verbatim into
    `.sputnik/cache`, which is one more reason to use `pass`, `op` or the
    environment instead.

## See also

- [Variables](variables.md) -- constants, dynamics and resolution order
- [Writing Tasks](tasks.md#shell-execution) -- `exec()` and `shell()`
