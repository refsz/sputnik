<?php

declare(strict_types=1);

namespace Sputnik\Executor;

use Sputnik\Exception\SputnikException;

final class ExecutionException extends SputnikException
{
    public function __construct(
        string $message,
        public readonly int $exitCode,
        public readonly string $errorOutput,
    ) {
        parent::__construct($message, $exitCode);
    }
}
