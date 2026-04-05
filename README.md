# Sputnik

[![CI](https://github.com/refsz/sputnik/actions/workflows/ci.yml/badge.svg)](https://github.com/refsz/sputnik/actions/workflows/ci.yml)
[![Latest Release](https://img.shields.io/github/v/release/refsz/sputnik)](https://github.com/refsz/sputnik/releases/latest)
[![Packagist](https://img.shields.io/packagist/v/refs/sputnik)](https://packagist.org/packages/refs/sputnik)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/packagist/dependency-v/refs/sputnik/php)](composer.json)

A PHP TaskRunner for project automation. Class-based tasks, context switching, environment-aware execution.

## What it does

Sputnik runs tasks defined as PHP classes. Each task is a single class with attributes -- no YAML actions, no function dumps, no DSL. You write normal PHP, Sputnik handles discovery, CLI, contexts, and shell routing.

```php
#[Task(name: 'deploy', description: 'Deploy the application', environment: 'container')]
final class DeployTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $ctx->shell('rsync -avz ./dist/ {{ deployPath }}/');
        $ctx->shellRaw('php artisan migrate --force');

        return TaskResult::success();
    }
}
```

```
$ php sputnik.phar deploy

Sputnik 0.1.0 │ .sputnik.dist.neon │ prod

▸ deploy · Deploy the application

  > rsync -avz ./dist/ /var/www/app/
  > php artisan migrate --force
✓ Done (1.24s)
```

## Install

### PHAR (recommended)

```bash
curl -Lo sputnik.phar https://github.com/refsz/sputnik/releases/latest/download/sputnik.phar
chmod +x sputnik.phar
php sputnik.phar init
```

Verify the download:

```bash
curl -Lo sputnik.phar.sha256 https://github.com/refsz/sputnik/releases/latest/download/sputnik.phar.sha256
sha256sum -c sputnik.phar.sha256
```

For IDE autocompletion you can additionally install via Composer: `composer require --dev refs/sputnik`. See [Installation](https://refsz.github.io/sputnik/installation/) for details.

## Key concepts

**Tasks** are PHP classes with `#[Task]` attributes. Options, arguments, and shell execution are built in. [Writing Tasks](https://refsz.github.io/sputnik/tasks/)

**Contexts** let you define named configurations -- different variables, different behavior. Switch with one command, no code changes. [Contexts](https://refsz.github.io/sputnik/contexts/)

**Templates** render files like `.env` with `{{ variable }}` syntax. Re-rendered automatically on context switch. [Templates](https://refsz.github.io/sputnik/templates/)

**Environments** route commands transparently between host and container. A task marked `environment: 'container'` is automatically wrapped with your Docker executor. [Environments](https://refsz.github.io/sputnik/environments/)

## Links

- [Documentation](https://refsz.github.io/sputnik) -- full docs, recipes, CLI reference
- [Releases](https://github.com/refsz/sputnik/releases) -- release notes and downloads
- [Contributing](CONTRIBUTING.md) -- development setup
- [Security](SECURITY.md) -- reporting vulnerabilities
- [License](LICENSE) -- MIT
