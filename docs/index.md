# Sputnik

A PHP TaskRunner for project automation. Class-based tasks, context switching, environment-aware execution.

---

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

## Get started

<div class="grid cards" markdown>

- :material-download: **[Installation](installation.md)** -- PHAR download or Composer
- :material-rocket-launch: **[Quick Start](quickstart.md)** -- init a project, run your first task
- :material-book-open-variant: **[Recipes](recipes.md)** -- practical patterns for common use cases
- :material-console: **[CLI Reference](cli.md)** -- all commands and flags

</div>

## Key concepts

**[Tasks](tasks.md)** are PHP classes with `#[Task]` attributes. Options, arguments, and shell execution are built in.

**[Contexts](contexts.md)** let you define named configurations -- different variables, different behavior. Switch with one command, no code changes.

**[Templates](templates.md)** render files like `.env` with `{{ variable }}` syntax. Re-rendered automatically on context switch.

**[Environments](environments.md)** route commands transparently between host and container. A task marked `environment: 'container'` is automatically wrapped with your Docker executor.

## Release Notes

Changes are documented in [GitHub Releases](https://github.com/refsz/sputnik/releases).
