# Installation

## PHAR

Download the latest release as a self-contained PHAR file:

```bash
curl -Lo sputnik.phar https://github.com/refsz/sputnik/releases/latest/download/sputnik.phar
chmod +x sputnik.phar
```

Verify the checksum:

```bash
curl -Lo sputnik.phar.sha256 https://github.com/refsz/sputnik/releases/latest/download/sputnik.phar.sha256
sha256sum -c sputnik.phar.sha256
```

Place the PHAR in your project root. You can commit it to version control so every team member uses the same version, or add it to `.gitignore` and download it as part of your setup.

Run with:

```bash
php sputnik.phar <command>
```

Use the PHAR when you want a zero-dependency setup. No Composer install needed in the project.

## Composer (optional)

If you want IDE autocompletion when writing tasks, you can additionally install via Composer:

```bash
composer require --dev refs/sputnik
```

This is not required for running Sputnik -- it only provides class definitions for PHPStorm and similar IDEs. Execution should still happen through the PHAR.

## Requirements

- PHP 8.2 or higher

## Next

- [Quick Start](quickstart.md) -- initialize a project and run your first task
- [CLI Reference](cli.md) -- all commands and flags
