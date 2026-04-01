<?php

declare(strict_types=1);

namespace Sputnik\Executor;

final class ExecutionResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
        public readonly float $duration,
        public readonly string $command,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getErrorOutput(): string
    {
        return $this->errorOutput;
    }

    public function getCombinedOutput(): string
    {
        return $this->output . $this->errorOutput;
    }

    public function assertSuccess(): self
    {
        if (!$this->isSuccessful()) {
            throw new ExecutionException(
                \sprintf('Command failed with exit code %d: %s', $this->exitCode, $this->command),
                $this->exitCode,
                $this->errorOutput,
            );
        }

        return $this;
    }
}
