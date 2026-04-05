---
hide:
  - navigation
  - toc
---

<div class="sputnik-hero" markdown>

# Sputnik

A PHP TaskRunner for project automation.
Class-based tasks, context switching, environment-aware execution.

[Get Started](quickstart.md){ .md-button .md-button--primary }
[View on GitHub](https://github.com/refsz/sputnik){ .md-button }

</div>

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

---

<div class="grid cards" markdown>

-   :material-code-braces: **Tasks**

    ---

    PHP classes with `#[Task]` attributes. Options, arguments, shell execution built in.

    [:octicons-arrow-right-24: Writing Tasks](tasks.md)

-   :material-swap-horizontal: **Contexts**

    ---

    Named configurations with variable overrides. Switch with one command, no code changes.

    [:octicons-arrow-right-24: Contexts](contexts.md)

-   :material-file-replace-outline: **Templates**

    ---

    Render files with `{{ variable }}` syntax. Re-rendered automatically on context switch.

    [:octicons-arrow-right-24: Templates](templates.md)

-   :material-docker: **Environments**

    ---

    Transparent command routing between host and container via configurable executor.

    [:octicons-arrow-right-24: Environments](environments.md)

</div>

---

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
