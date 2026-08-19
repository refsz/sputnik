# Changelog

All notable changes to Sputnik are recorded here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Sputnik
follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html), with the caveat
that the major version is still `0`: a minor release may change behaviour, and every
such change is listed below under **Changed**.

This file records what matters when upgrading -- new capabilities, changed
behaviour, and fixes with user-visible effect. The complete list of merged pull
requests is in the generated notes of each
[GitHub release](https://github.com/refsz/sputnik/releases).

## [Unreleased]

### Added

- `variables.secrets` declares sensitive variables. Their values are replaced with
  `***` in everything Sputnik prints -- the echoed command, streamed command output,
  failure messages, log lines and `$ctx->writeln()`. Secrets resolve on first use, so
  a `pass show` or `op read` definition does not prompt for tasks that never touch
  them. See [Secrets](https://refsz.github.io/sputnik/variables/#secrets) for the
  exact guarantee and its documented limits.
- `type: env` reads a variable from the environment, for secrets and dynamic
  variables alike.
- `$ctx->render()` substitutes variables in any string, with the same parser and the
  same variables a template file gets, for tasks that have to produce file content
  themselves.
- A one-shot `--context` flag, and built-in option names are now reserved so a task
  cannot shadow them.

### Changed

- `TaskContext::__construct()` takes a required `TemplateRenderer`. Only `TaskRunner`
  constructs a context, so this affects code that builds one directly, such as tests.
- `VariableResolver::__construct()` takes a `SecretRegistry` as its fourth parameter.
  It defaults, so existing callers keep working.
- `shell()` interpolates through the template parser instead of its own regular
  expression. `{{! required }}` now fails with `MissingVariableException` instead of
  being passed to the shell verbatim, `\{\{ ... \}\}` stays literal, and array values
  use the same JSON flags as templates. A missing optional variable still becomes an
  empty quoted argument.
- A variable that resolves to `null` counts as absent: `{{ name | "default" }}` uses
  its default, and `{{! name }}` fails. An empty string remains a value.
- Configuration errors are reported differently from fatal errors in CLI output.
- A task declared for a container without an executor, and a host task started inside
  a container, now fail instead of running in the wrong place.

### Fixed

- The one-shot `--context` override now re-renders templates back to the persisted
  context after the run. Since 0.1 that step never executed -- everything after the
  command in `bin/sputnik` was dead code -- and once reachable it still rendered with
  the wrong context, because only the template engine switched and not the variable
  resolver.
- The compiled container cache is invalidated when Sputnik's own sources change.
  0.1.x added `Application::VERSION` to the cache key, but outside a PHAR that
  constant is the unresolved `@package_version@` placeholder, so an upgrade never
  invalidated `.sputnik/cache` -- and any new service on the boot path turned that
  into a fatal error on every command until the cache was deleted by hand.
- `TemplateRenderer::canRender()` and `render()` can no longer disagree: both derive
  from the same resolution, so a template that passes the check cannot fail the
  render.

### Removed

- Unused dependencies `nette/utils` and `symfony/var-dumper`.

## [0.1.0] - 2026-04-03

First release: class-based tasks with attributes, contexts, the variable system with
constants and dynamic variables, template rendering, event listeners, environment
routing between host and container, the `init` command, and a distributable PHAR.

[Unreleased]: https://github.com/refsz/sputnik/compare/0.1.0...HEAD
[0.1.0]: https://github.com/refsz/sputnik/releases/tag/0.1.0
