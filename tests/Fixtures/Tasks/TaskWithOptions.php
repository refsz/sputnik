<?php

declare(strict_types=1);

namespace Sputnik\Tests\Fixtures\Tasks;

use Sputnik\Attribute\Argument;
use Sputnik\Attribute\Option;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(
    name: 'test:with-options',
    description: 'A test task with options',
    group: 'test',
)]
final class TaskWithOptions implements TaskInterface
{
    #[Option(name: 'mode', shortcut: 'm', description: 'Build mode', default: 'development')]
    private string $mode;

    #[Option(name: 'verbose', shortcut: 'v', description: 'Verbose output', default: false)]
    private bool $verbose;

    #[Argument(name: 'target', description: 'Build target', default: 'app')]
    private string $target;

    public function __invoke(TaskContext $ctx): TaskResult
    {
        $mode = $ctx->option('mode', 'development');
        $verbose = $ctx->option('verbose', false);
        $target = $ctx->argument('target', 'app');

        $command = "build --mode={$mode} --target={$target}";
        if ($verbose) {
            $command .= ' --verbose';
        }

        $result = $ctx->shell($command);

        return $result->isSuccessful()
            ? TaskResult::success('Build completed')
            : TaskResult::failure($result->getErrorOutput());
    }
}
