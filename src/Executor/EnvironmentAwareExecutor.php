<?php

declare(strict_types=1);

namespace Sputnik\Executor;

use Sputnik\Environment\EnvironmentDetector;

final class EnvironmentAwareExecutor implements ExecutorInterface
{
    public function __construct(
        private readonly ExecutorInterface $inner,
        private readonly EnvironmentDetector $detector,
        private readonly ?string $environment,
    ) {
    }

    /**
     * @param list<string>|string                                                                $command
     * @param array{cwd?: string, env?: array<string, string>, timeout?: float|null, tty?: bool} $options
     */
    public function execute(array|string $command, array $options = []): ExecutionResult
    {
        $wrapped = $this->detector->wrapCommand($command, $this->environment);

        return $this->inner->execute($wrapped, $options);
    }
}
