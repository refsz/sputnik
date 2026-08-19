---
hide:
  - navigation
  - toc
  - title
---

<div class="grid sputnik-hero" markdown>

<div markdown>

<p class="sputnik-kicker">PHP task automation with actual structure</p>

# Sputnik

A PHP TaskRunner that lets you define project automation as clean, testable PHP classes. No YAML, no DSL -- just normal code with attributes.

Define tasks with options, arguments, and shell execution. Switch contexts for different environments. Route commands transparently between host and container. Distribute as a single PHAR file.

[Get Started](quickstart.md){ .md-button .md-button--primary }
[Installation](installation.md){ .md-button }
[GitHub](https://github.com/refsz/sputnik){ .md-button }

<span class="sputnik-pill">PHAR-first</span>
<span class="sputnik-pill">Context-aware</span>
<span class="sputnik-pill">Container routing</span>
<span class="sputnik-pill">Secret masking</span>
<span class="sputnik-pill">PHP 8.3+</span>

</div>

<div class="sputnik-terminal-window" markdown>

```bash
$ php sputnik.phar deploy

🛰  Sputnik v0.2.0 │ .sputnik.dist.neon │ prod

▸ deploy · Deploy the application

  > rsync -avz ./dist/ /var/www/
  > php artisan migrate --force
✓ Done (1.24s)
```

</div>

</div>

<div class="grid cards sputnik-signal-grid" markdown>

-   :material-code-json: **Normal PHP**

    ---

    Tasks are plain PHP classes. No YAML workflow language, no custom mini-language, no "magic function dump".

-   :material-layers-triple: **Project state built in**

    ---

    Contexts, variables, templates, listeners, and runtime state are part of the model instead of ad-hoc shell conventions.

-   :material-console-network: **CLI that matches real work**

    ---

    Designed for project builds, deploys, Docker workflows, context switching, and repeatable local automation.

</div>

```php
#[Task(name: 'deploy', description: 'Deploy the application', environment: 'container')]
final class DeployTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $ctx->shell('rsync -avz ./dist/ {{ deployPath }}/');
        $ctx->exec(['php', 'artisan', 'migrate', '--force']);

        return TaskResult::success();
    }
}
```

```bash
$ php sputnik.phar deploy

🛰  Sputnik v0.2.0 │ .sputnik.dist.neon │ prod

▸ deploy · Deploy the application

  > rsync -avz ./dist/ /var/www/app/
  > php artisan migrate --force
✓ Done (1.24s)
```

## Why teams pick Sputnik

<div class="grid cards" markdown>

-   :material-code-braces: **Tasks**

    ---

    PHP classes with `#[Task]` attributes. Options, arguments, and shell execution built in.

    [:octicons-arrow-right-24: Writing Tasks](tasks.md)

-   :material-swap-horizontal: **Contexts**

    ---

    Named configurations with variable overrides. Switch with one command, no code changes.

    [:octicons-arrow-right-24: Contexts](contexts.md)

-   :material-file-replace-outline: **Templates**

    ---

    Render files with `{{ variable }}` syntax. Re-rendered automatically on context switch.

    [:octicons-arrow-right-24: Templates](templates.md)

-   :material-key-outline: **Secrets**

    ---

    Variables Sputnik refuses to print. Masked in echoed commands, streamed output and log lines.

    [:octicons-arrow-right-24: Secrets](secrets.md)

-   :material-docker: **Environments**

    ---

    Transparent command routing between host and container via configurable executor.

    [:octicons-arrow-right-24: Environments](environments.md)

</div>

## Start here

<div class="grid cards" markdown>

-   :material-rocket-launch: **Quick Start**

    ---

    Initialize a project and run your first task in under a minute.

    [:octicons-arrow-right-24: Quick Start](quickstart.md)

-   :material-book-open-variant: **Recipes**

    ---

    Practical patterns for builds, deploys, Docker, templates, and more.

    [:octicons-arrow-right-24: Recipes](recipes.md)

-   :material-console: **CLI Reference**

    ---

    All commands, flags, and reserved names.

    [:octicons-arrow-right-24: CLI Reference](cli.md)

-   :material-tag: **Releases**

    ---

    Release notes and PHAR downloads.

    [:octicons-arrow-right-24: GitHub Releases](https://github.com/refsz/sputnik/releases)

</div>

## Fits best when

<div class="grid cards sputnik-fit-grid" markdown>

-   :material-check-circle-outline: **You want structure, not scripts everywhere**

    ---

    Shell snippets still exist, but they live inside typed PHP tasks with explicit options, arguments, and metadata.

-   :material-check-circle-outline: **You switch between dev, staging, and production a lot**

    ---

    Contexts and template regeneration make environment changes explicit instead of buried in copied commands.

-   :material-check-circle-outline: **You need one distributable binary**

    ---

    PHAR distribution keeps execution simple for teams and CI while Composer remains optional for editor support.

</div>
