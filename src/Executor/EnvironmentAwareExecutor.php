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

    public function execute(string $command, array $options = []): ExecutionResult
    {
        $wrapped = $this->detector->wrapCommand($command, $this->environment);

        return $this->inner->execute($wrapped, $options);
    }
}
