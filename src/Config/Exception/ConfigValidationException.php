<?php

declare(strict_types=1);

namespace Sputnik\Config\Exception;

use Sputnik\Exception\SputnikException;

final class ConfigValidationException extends SputnikException
{
    /**
     * @var list<string>
     */
    private array $errors = [];

    /**
     * @param list<string> $errors
     */
    public static function withErrors(array $errors): self
    {
        $message = "Configuration validation failed:\n- " . implode("\n- ", $errors);

        $exception = new self($message);
        $exception->errors = $errors;

        return $exception;
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
