# Quick Start

## Initialize a project

```bash
php sputnik.phar init
```

This creates:

- `.sputnik.dist.neon` -- your project configuration
- `sputnik/ExampleTask.php` -- a sample task to get started

## Run the example task

```bash
php sputnik.phar example
```

```
Sputnik 0.1.0 | .sputnik.dist.neon | local

> example - An example task to get you started

Hello, World!
Done (0.00s)
```

## Create your own task

Create a PHP file in your task directory (default: `sputnik/`):

```php
<?php

declare(strict_types=1);

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(
    name: 'greet',
    description: 'Say hello',
)]
final class GreetTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $ctx->shellRaw('echo "Hello from Sputnik!"');

        return TaskResult::success();
    }
}
```

Run it:

```bash
php sputnik.phar greet
```

## Next steps

- [Configuration Reference](configuration.md) -- configure contexts, variables, templates
- [Writing Tasks](tasks.md) -- options, arguments, shell execution, sub-tasks
- [Variables](variables.md) -- dynamic variables, runtime overrides
- [Templates](templates.md) -- file templating with variable substitution
