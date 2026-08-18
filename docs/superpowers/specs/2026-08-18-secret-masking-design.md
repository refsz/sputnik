# Secret Masking — Design

Date: 2026-08-18
Status: approved for planning

## Problem

Sputnik interpolates variables into shell commands and prints what it runs. A
variable holding a token, a password or a DSN therefore reaches the console
through several paths:

| Path | What leaks |
|---|---|
| `src/Console/SputnikOutput.php:41` | The interpolated command is echoed on every `shell()` call, with the quoted secret in it |
| `src/Executor/ShellExecutor.php:112` | Raw stdout and stderr of the process, e.g. `curl -v` echoing an auth header |
| `src/Executor/ExecutionResult.php:43` | `assertSuccess()` builds the exception message from the full command |
| `src/Task/TaskRunner.php:115` | Catches every `Throwable`, logs the message and returns it as `TaskResult::failure()` — a failing command prints the secret twice |
| `src/Console/ConsoleLogger.php` | Anything a task logs |

Every one of these is a display path. None of them is solvable by the task
author, because the command string is assembled by Sputnik.

## Scope

Masking applies at the **display boundary only**. Everything Sputnik writes is
redacted; the data a task receives stays untouched.

Promise: *Sputnik prints nothing that a declared secret resolves to.*

Not promised: *a task cannot leak*. `ExecutionResult::getOutput()` returns raw
output so a task can still parse it, and a task that bypasses Sputnik's output
(`echo`, `file_put_contents`) bypasses masking.

## Configuration

Secrets are declared in their own source under `variables`, as a sibling of
`constants` and `dynamics`:

```neon
variables:
    constants:
        dbHost: localhost

    dynamics:
        userId:
            type: command
            command: "id -u"

    secrets:
        apiToken:
            type: command
            command: "pass show project/api"

        dbPassword:
            type: env
            name: DB_PASSWORD

        legacyKey: "literal-value"
```

Declaring a secret and classifying it as sensitive are the same act. There is no
second list to keep in sync, so a renamed or newly added secret cannot silently
lose its classification.

Supported definitions:

- `type: command` and `type: script` — the same grammar and the same
  `DynamicVariableResolver` as `dynamics`
- `type: env` with `name` — reads an environment variable, new in this change
- a plain scalar — a literal value; allowed, but the documentation points at
  `pass`, `op` or the environment instead, because a secret does not belong in a
  committed config file

`type: git` and `type: system` are not accepted here: a branch name or a
hostname is never a secret, and rejecting them keeps the section honest.

A name declared in more than one of `constants`, `dynamics` and `secrets` is a
configuration error (`InvalidConfigException`), not a silent precedence win —
otherwise a `constants` entry could quietly declassify a secret.

Context-level overrides of `secrets` are out of scope for this change, matching
`dynamics`, which is not context-overridable either. Runtime overrides work:
`-D apiToken=xyz` replaces the value, and the classification stays because it is
attached to the name.

## Resolution

Secrets resolve **lazily**, on first access of that specific name. Dynamics
resolve eagerly — `VariableResolver::initialize()` resolves all of them as soon
as any variable is touched — which is wrong for a secret: `pass show` triggers a
GPG passphrase prompt and `op read` a biometric check, so eager resolution would
prompt on every task that reads any variable, including tasks that never use the
secret.

Consequences:

- `has()` returns `true` for a declared secret without resolving it, so
  `TemplateRenderer::getMissingVariables()` and `canRender()` treat a declared
  secret as available and do not trigger a prompt for a diagnostic.
- A secret that fails to resolve is `null` plus a warning. `{{! apiToken }}`
  then fails with `MissingVariableException`, which follows from the
  null-as-absent semantics already in the renderer.
- The registry learns a value exactly when it is resolved. A secret that was
  never resolved cannot appear in any output, so there is nothing to redact.

## Masking

Three new classes in a new `Sputnik\Secret` namespace:

**`SecretRegistry`** — holds the declared secret names, taken from the config at
boot, and the resolved values, handed over by `VariableResolver` at resolution
time. Answers `isSecret(string $name): bool` and records which values are short
enough to cause collateral masking.

**`SecretRedactor`** — `redact(string $text): string`. Replaces every resolved
secret value with `***`, and also its `escapeshellarg()` form, because that is
what appears in an echoed command. Values are replaced longest first, so a
secret that contains another secret as a substring cannot leave fragments
behind.

Matching depends on the value's length:

| Value length | Replacement |
|---|---|
| 8 characters or more | Plain substring. Tokens and DSNs appear inside larger strings (`Bearer ghp_…`, `postgres://user:pw@host`) |
| fewer than 8 characters | Word boundaries only, `/(?<![\w-])value(?![\w-])/`. `abc` inside `abcdef` stays intact, a standalone `abc` is masked |

Short and boolean values are masked, not skipped. Since masking is display-only,
over-masking costs readability while under-masking leaks a secret — and a
masking feature that silently declines to mask is exactly the half-true promise
this project rejects. A boolean secret renders as `true`/`false` through the
verbatim value formatter and is then masked at word boundaries, which does hit
unrelated occurrences; the warning below says so.

**`RedactingOutput`** and **`RedactingConsoleOutput`** — a decorator around
`OutputInterface`, plus a subclass implementing `ConsoleOutputInterface` so that
wrapping a `ConsoleOutput` keeps `getErrorOutput()` and Symfony's error rendering
on stderr. Applied once in
`bin/sputnik`, where the `ConsoleOutput` is created and handed to
`$app->run(null, $output)`. Every console channel passes through that one
object, so the decorator covers the command echo, streamed process output,
failure messages, the logger and `$ctx->writeln()` by construction, instead of
five injection points that have to be maintained and can each be forgotten.
Wrapping there rather than in `Application::doRun()` also covers Symfony's own
`renderThrowable()` and the `catch` blocks in `bin/sputnik:92-100`, which print
bootstrap errors, and it leaves `Application` untouched.

The registry is a container service. The container is compiled and cached
(`ContainerLoader`, `.sputnik/cache`), so a runtime object cannot travel through
extension parameters; instead `bin/sputnik` builds the `Kernel` first and then
wraps the output with the redactor the container already holds:

```php
$output = new ConsoleOutput();
$kernel = new Kernel(workingDir: $workingDir, contextName: $contextOverride);
$output = new RedactingConsoleOutput($output, $kernel->getSecretRedactor());
$exitCode = $kernel->createApplication()->run(null, $output);
```

The registry starts empty and fills as secrets resolve, so the wrap order costs
nothing: no secret can be known before the container exists.

Redaction sits at the output layer, not in an `OutputFormatter` decorator,
because `ShellExecutor::streamOutput()` writes with `OutputInterface::OUTPUT_RAW`
- which bypasses formatting entirely. A formatter would miss the largest leak
path.

## Components

| File | Change |
|---|---|
| `src/Secret/SecretRegistry.php` | new |
| `src/Secret/SecretRedactor.php` | new |
| `src/Secret/RedactingOutput.php` | new |
| `src/Config/Configuration.php` | `getSecrets()`, analogous to `getDynamics()` |
| `src/Variable/VariableResolver.php` | lazy resolution path for secret names, registry notification, collision check |
| `src/Variable/DynamicVariableResolver.php` | one `match` arm for `type: env` |
| `src/Secret/RedactingConsoleOutput.php` | new - the `ConsoleOutputInterface` variant, so stderr keeps working |
| `bin/sputnik` | wrap `ConsoleOutput` after the `Kernel` is built |
| `src/Kernel.php` | `getSecretRedactor()` accessor |
| `src/Task/TaskRunner.php` | log the registry's diagnostics after a task run |
| `src/DependencyInjection/SputnikExtension.php` | register registry and redactor, inject the registry into `VariableResolver` and `TaskRunner` |
| `docs/variables.md`, `docs/configuration.md` | document the section, the guarantees and the limits |

## Data flow

```text
variables.secrets (config)
        │  names at boot
        ▼
   SecretRegistry ◄──── remember(name, value) ──── VariableResolver (lazy, first access)
        │                                                   │
        │ values                                            ▼
        ▼                                         DynamicVariableResolver
   SecretRedactor                                 (command | script | env | literal)
        │
        ▼
   RedactingOutput ──► everything Sputnik writes
```

## Diagnostics

- **Name collision** across `constants`, `dynamics` and `secrets` —
  `InvalidConfigException` when variables initialise.
- **Unresolvable secret** — value `null` and a warning naming the secret.
- **Short value** — a warning at `warning` level, visible with `-v`:
  `secret 'x' has a short value; unrelated output may be masked too`. Advisory
  only: the value *is* masked, so this is about readability, not exposure.

## Limits

These belong in the documentation, not in the guarantee:

- Rendered template files contain real values. That is their purpose.
- `ps aux` shows the real command line. A task that wants to avoid that passes
  the secret through `shell(..., ['env' => [...]])` instead of the command line.
- A task using `echo` or `file_put_contents()` writes outside Symfony's output
  and is not redacted.
- `ExecutionResult` carries raw output and the raw command, by decision.
- Errors raised before the container is built cannot be redacted, because no
  secret is known at that point. A NEON parse error that quotes a line containing a
  literal secret is the one case where this is visible — another reason for the
  documentation to point at `pass`, `op` and the environment instead of literals.

## Rejected alternatives

**A global list of secret names** (`variables.secrets: [apiToken, …]` next to
the definitions) — a second place that must stay in sync. When it drifts, the
failure direction is a silent leak.

**A `sensitive: true` flag on the definition** — constants are plain scalars in
NEON, so a flag would force them into a structure and break the schema. It also
cannot express lazy resolution without putting two resolution strategies into
`dynamics`, selected by a flag.

**Automatic masking by naming convention** (`*token*`, `*password*`) — an
invisible rule that hits `publicKey` and misses `dsn`.

**Masking inside `ExecutionResult`** — a task parsing output would receive
altered data by default, and the resulting bugs are hard to see.

**Structural masking of the command echo** — rendering the command twice, once
with real values for the process and once with `***` for display, is exact and
needs no substring matching. Rejected for this change because the display
variant would have to travel through `ExecutorInterface::execute()` and
`EnvironmentAwareExecutor`, which rewrites commands and would have to rewrite
both variants. Value redaction already covers the echo, including short values,
via the word-boundary rule. This stays the documented upgrade path if
over-masking turns out to be a real problem.

**A placeholder naming the secret** (`***{apiToken}`) — more informative, but it
changes output shape for no security gain. `***` is fixed.

## Test plan

Unit:

- `SecretRegistry`: declared names are recognised; values are remembered on
  resolution; short values are flagged.
- `SecretRedactor`: value replaced; `escapeshellarg()` form replaced; longest
  value first when one secret contains another; substring replacement at 8
  characters and above; word-boundary behaviour below 8, including that `abc`
  inside `abcdef` survives; boolean value masked as `true`/`false`.
- `RedactingOutput`: redacts `write()` and `writeln()`; passes verbosity,
  formatter and decoration calls through unchanged; leaves style tags intact.
- `VariableResolver`: a declared secret is not resolved until accessed —
  asserted with a counting double, not a mock expectation; `has()` does not
  resolve; a runtime override of a secret name stays classified; a name in two
  sources throws `InvalidConfigException`; an unresolvable secret is `null`.
- `DynamicVariableResolver`: `type: env` reads the variable and returns `null`
  when it is unset.

Integration:

- A task that runs `shell()` with a secret in the command: captured output
  contains `***` and never the value, in the command echo and in the streamed
  output.
- A failing command with a secret: the failure message is redacted on both
  paths, logger and `TaskResult`.
- `ExecutionResult::getOutput()` still returns the raw value, proving the
  boundary decision.
- A rendered template still contains the real value.

End to end, through the real binary:

- A task whose command carries a literal secret: neither stdout nor stderr of
  `bin/sputnik` contains the value, on success and on failure.
