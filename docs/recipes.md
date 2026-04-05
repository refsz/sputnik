# Recipes

Practical patterns for common Sputnik use cases. These are starting points -- adapt paths, commands, and variable names to your project.

## Simple build task

```php
#[Task(name: 'build', description: 'Build the project')]
final class BuildTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $ctx->shellRaw('composer install --no-dev --optimize-autoloader');
        $ctx->shellRaw('npm ci && npm run build');

        return TaskResult::success();
    }
}
```

```bash
sputnik build
```

## Container task with Docker

Config:

```neon
environment:
    executor: "docker compose exec -T app {command}"
```

Task:

```php
#[Task(name: 'db:migrate', description: 'Run database migrations', environment: 'container')]
final class MigrateTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $ctx->shellRaw('php artisan migrate --force');

        return TaskResult::success('Migrations applied');
    }
}
```

```bash
sputnik db:migrate
# On host: automatically wraps as "docker compose exec -T app php artisan migrate --force"
# In container: runs directly
```

## Context-based .env generation

Config:

```neon
contexts:
    dev:
        description: Local development
        variables:
            constants:
                appEnv: dev
                debug: true
    prod:
        description: Production
        variables:
            constants:
                appEnv: prod
                debug: false

variables:
    constants:
        dbHost: localhost
        dbName: myapp

templates:
    env:
        src: templates/.env.dist
        dist: .env
        overwrite: always
```

Template (`templates/.env.dist`):

```
APP_ENV={{ appEnv }}
DEBUG={{ debug }}
DB_HOST={{ dbHost }}
DB_NAME={{ dbName }}
```

Switch context and the `.env` is automatically re-rendered:

```bash
sputnik context:switch prod
# .env now contains APP_ENV=prod, DEBUG=false
```

## Runtime variable overrides

```php
#[Task(name: 'deploy', description: 'Deploy the application')]
final class DeployTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $target = $ctx->get('deployTarget', 'staging');
        $ctx->info("Deploying to {$target}");

        $ctx->shell('rsync -avz ./dist/ {{ deployTarget }}:/var/www/');

        return TaskResult::success("Deployed to {$target}");
    }
}
```

```bash
sputnik deploy -D deployTarget=production
```

## Task with options and arguments

```php
use Sputnik\Attribute\Argument;
use Sputnik\Attribute\Option;

#[Task(name: 'db:seed', description: 'Seed database tables', environment: 'container')]
final class SeedTask implements TaskInterface
{
    #[Argument(name: 'table', description: 'Table to seed')]
    private ?string $table;

    #[Option(name: 'count', description: 'Number of rows', shortcut: 'c', type: 'int', default: 10)]
    private int $count;

    #[Option(name: 'truncate', description: 'Truncate before seeding', default: false)]
    private bool $truncate;

    public function __invoke(TaskContext $ctx): TaskResult
    {
        $table = $ctx->argument('table') ?? 'all';
        $count = $ctx->option('count');

        if ($ctx->option('truncate')) {
            $ctx->shellRaw("php artisan db:truncate {$table}");
        }

        $ctx->shellRaw("php artisan db:seed --table={$table} --count={$count}");

        return TaskResult::success("Seeded {$table} with {$count} rows");
    }
}
```

```bash
sputnik db:seed users --count 50 --truncate
sputnik db:seed users -c 50
```

## Listener on context switch

```php
use Sputnik\Attribute\AsListener;
use Sputnik\Event\ContextSwitchedEvent;
use Sputnik\Executor\ExecutorInterface;

#[AsListener(event: ContextSwitchedEvent::class, priority: -10, environment: 'container')]
final class ClearCacheOnContextSwitch
{
    public function __construct(
        private readonly ExecutorInterface $executor,
    ) {}

    public function __invoke(ContextSwitchedEvent $event): void
    {
        if (!$event->hasChanged()) {
            return;
        }

        $this->executor->execute('php artisan cache:clear');
    }
}
```

Runs automatically after every context switch, after templates have been re-rendered (priority -10 runs after the built-in listeners at 100 and 0).

## One-shot context override

Run a single task in a different context without switching:

```bash
sputnik --context prod deploy
```

The persisted context is not changed. Templates are rendered with the override context for this run, then restored afterwards.

## Host-only task

```php
#[Task(name: 'docker:start', description: 'Start containers', environment: 'host')]
final class DockerStartTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $ctx->shellRaw('docker compose up -d');

        return TaskResult::success();
    }
}
```

This task will fail with an error if executed inside a container, preventing accidental nested container operations.
