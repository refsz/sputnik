# Contributing to Sputnik

Thanks for your interest in contributing to Sputnik!

## Getting Started

```bash
git clone git@github.com:refsz/sputnik.git
cd sputnik
composer install
```

## Development

### Running Tests

```bash
vendor/bin/phpunit
```

### Static Analysis

```bash
vendor/bin/phpstan analyse
```

### Code Style

```bash
# Check
vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix
vendor/bin/php-cs-fixer fix
```

### Rector

```bash
# Check
vendor/bin/rector --dry-run

# Apply
vendor/bin/rector
```

### Building the PHAR

```bash
php -d phar.readonly=0 vendor/bin/box compile
```

## Releasing

Releases are cut from a tag. `.github/workflows/release.yml` runs the test suite and
PHPStan, builds the PHAR, verifies and smoke-tests it, writes a checksum and creates
the GitHub release. Box resolves `Application::VERSION` from the tag, so the version a
user sees comes from the tag alone -- there is no version constant to edit.

1. Make sure `main` is green and holds everything the release should contain
2. Move the `## [Unreleased]` section of `CHANGELOG.md` under the new version with
   today's date, and update the compare links at the bottom of that file
3. Update the version shown in the README terminal mockup
4. Commit, then tag: `git tag 0.2.0 && git push origin 0.2.0`
5. Watch the Release workflow, then check the published PHAR:
   `sputnik --version` must print the tag, never `@package_version@`

## Pull Requests

1. Fork the repo and create a feature branch
2. Make sure all tests pass and static analysis is clean
3. Follow the existing code style (enforced by PHP-CS-Fixer)
4. Write tests for new functionality
5. Keep PRs focused -- one feature or fix per PR
6. Use a [Conventional Commits](https://www.conventionalcommits.org/) PR title
   (`feat: ...`, `fix: ...`) -- PRs are squash-merged and the title becomes the
   commit on `main`
7. If the PR changes behaviour, add an entry to the `Unreleased` section of
   `CHANGELOG.md` in the same PR -- the changelog is written while the knowledge
   is fresh, not reconstructed at release time

## Reporting Bugs

Open an issue with:

- PHP version
- Sputnik version (`sputnik --version`)
- Steps to reproduce
- Expected vs actual behavior

## Security Issues

Please see [SECURITY.md](SECURITY.md) for reporting vulnerabilities. Do not open public issues for security bugs.
