# Installation

## PHAR (recommended)

Download the latest release:

```bash
curl -Lo sputnik.phar https://github.com/refsz/sputnik/releases/latest/download/sputnik.phar
chmod +x sputnik.phar
```

Verify the checksum:

```bash
curl -Lo sputnik.phar.sha256 https://github.com/refsz/sputnik/releases/latest/download/sputnik.phar.sha256
sha256sum -c sputnik.phar.sha256
```

Place the PHAR in your project root and commit it to version control. This ensures every team member uses the same version.

## Composer

```bash
composer require --dev refs/sputnik
```

This installs Sputnik as a dev dependency. Useful for IDE autocompletion when writing tasks.

## Requirements

- PHP 8.2 or higher
