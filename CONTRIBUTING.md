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

## Pull Requests

1. Fork the repo and create a feature branch
2. Make sure all tests pass and static analysis is clean
3. Follow the existing code style (enforced by PHP-CS-Fixer)
4. Write tests for new functionality
5. Keep PRs focused -- one feature or fix per PR

## Reporting Bugs

Open an issue with:

- PHP version
- Sputnik version (`sputnik --version`)
- Steps to reproduce
- Expected vs actual behavior

## Security Issues

Please see [SECURITY.md](SECURITY.md) for reporting vulnerabilities. Do not open public issues for security bugs.
