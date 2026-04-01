<?php

declare(strict_types=1);

namespace Sputnik\Context;

use Sputnik\Exception\SputnikException;

final class ContextNotFoundException extends SputnikException
{
    /**
     * @param array<string> $available
     */
    public function __construct(
        public readonly string $contextName,
        public readonly array $available = [],
    ) {
        $message = \sprintf('Context not found: %s', $contextName);

        if ($available !== []) {
            $message .= \sprintf('. Available contexts: %s', implode(', ', $available));
        }

        parent::__construct($message);
    }

    /**
     * @param array<string> $available
     */
    public static function forContext(string $contextName, array $available = []): self
    {
        return new self($contextName, $available);
    }
}
