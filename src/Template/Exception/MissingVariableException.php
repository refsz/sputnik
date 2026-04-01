<?php

declare(strict_types=1);

namespace Sputnik\Template\Exception;

use Sputnik\Exception\SputnikException;

final class MissingVariableException extends SputnikException
{
    /**
     * @param list<string> $variables
     */
    public function __construct(
        public readonly array $variables,
        public readonly ?string $templatePath = null,
    ) {
        $varList = implode(', ', $variables);
        $message = \count($variables) === 1
            ? 'Missing required variable: ' . $varList
            : 'Missing required variables: ' . $varList;

        if ($templatePath !== null) {
            $message .= ' in template: ' . $templatePath;
        }

        parent::__construct($message);
    }

    public static function forVariable(string $variable, ?string $templatePath = null): self
    {
        return new self([$variable], $templatePath);
    }
}
