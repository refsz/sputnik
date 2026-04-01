# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- CI workflows for PHPUnit, PHPStan, PHP-CS-Fixer, and Rector
- Release workflow with PHAR build and SHA256 checksum
- Dependabot for Composer and GitHub Actions
- E2E tests for CLI binary
- Reserved command name protection in task discovery
- Alias collision detection for task names vs built-in commands

### Fixed
- Runtime variable overrides now propagate to subtasks
- Run command option parsing using task metadata (supports `--opt value` and shortcuts)
- TaskFailedEvent dispatched for all throwables, not just SputnikException
- Dynamic variable resolver respects configured workingDir
- Config display shows "no config" when no config files exist
- `hasConfig()` detects local-only `.sputnik.neon` configs
- Documentation discrepancies (composite variable syntax, git property names)

### Changed
- Default task directory changed from `tasks/` to `sputnik/`
- Extracted shared task execution logic into `TaskExecutionTrait`
- Moved `TaskCommand` to `Console\Command` namespace
- PHAR version resolved from git tag via Box `git` config

### Removed
- `SelfUpdateCommand` (use GitHub Releases for updates)
- `SPECIFICATION.md` (replaced by documentation in `docs/`)
