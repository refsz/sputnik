<?php

declare(strict_types=1);

namespace Sputnik\Task;

final class TaskResult
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        public readonly TaskStatus $status,
        public readonly ?string $message = null,
        public readonly array $data = [],
        public readonly ?int $exitCode = null,
        public readonly ?float $duration = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function success(?string $message = null, array $data = []): self
    {
        return new self(
            status: TaskStatus::Success,
            message: $message,
            data: $data,
            exitCode: 0,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function failure(string $message, array $data = [], int $exitCode = 1): self
    {
        return new self(
            status: TaskStatus::Failure,
            message: $message,
            data: $data,
            exitCode: $exitCode,
        );
    }

    public static function skipped(string $reason): self
    {
        return new self(
            status: TaskStatus::Skipped,
            message: $reason,
            exitCode: 0,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status === TaskStatus::Success;
    }

    public function isFailure(): bool
    {
        return $this->status === TaskStatus::Failure;
    }

    public function isSkipped(): bool
    {
        return $this->status === TaskStatus::Skipped;
    }

    public function withDuration(float $duration): self
    {
        return new self(
            status: $this->status,
            message: $this->message,
            data: $this->data,
            exitCode: $this->exitCode,
            duration: $duration,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function withData(array $data): self
    {
        return new self(
            status: $this->status,
            message: $this->message,
            data: array_merge($this->data, $data),
            exitCode: $this->exitCode,
            duration: $this->duration,
        );
    }
}
