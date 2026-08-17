# Installation

=== "PHAR (recommended)"

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

    Run with:

    ```bash
    php sputnik.phar <command>
    ```

=== "Composer (optional)"

    If you want IDE autocompletion when writing tasks:

    ```bash
    composer require --dev refs/sputnik
    ```

    Run with:

    ```bash
    vendor/bin/sputnik <command>
    ```

!!! tip
    The PHAR is the recommended way to run Sputnik. Composer install is optional and primarily useful for IDE autocompletion. You can use both together -- Composer for class definitions, PHAR for execution.

## Requirements

!!! note
    PHP 8.2 or higher is required.

## Next

- [Quick Start](quickstart.md) -- initialize a project and run your first task
- [CLI Reference](cli.md) -- all commands and flags
