# CLAUDE.md

Sputnik is a PHP task runner (PHP 8.3+, Symfony Console, Nette DI). Everything —
code, commits, docs — is written in English.

## Commits and PR titles

This repository uses [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope)?: subject
```

- Types: `feat`, `fix`, `docs`, `test`, `build`, `ci`, `refactor`, `perf`, `chore`.
- Subject in lowercase, imperative mood, no trailing period.
- Breaking changes: `!` after the type/scope, e.g. `feat!: ...`.
- **PR titles follow the same format.** PRs are squash-merged and the PR title
  becomes the commit on `main`, so the title is what tooling and history see.
- Do NOT use the `[TYPE]` bracket convention from other projects.

## Checks

All of these must pass before a commit is proposed:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse --no-progress   # zero errors; never add ignores or baselines
vendor/bin/php-cs-fixer fix
vendor/bin/rector process --dry-run
mkdocs build --strict --site-dir /tmp/docs-check   # when docs/ or mkdocs.yml changed
```

## Conventions

- `declare(strict_types=1);` in every PHP file.
- Comments only where the code cannot express the why; no DocBlocks that repeat
  the signature.
- Tests mirror `src/` under `tests/Unit|Integration|E2E`; test doubles live in
  `tests/Support/Doubles/`.
- Changes that alter behaviour get an entry in the `Unreleased` section of
  `CHANGELOG.md` in the same PR.
