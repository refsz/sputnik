<?php

declare(strict_types=1);

namespace Sputnik\Template;

/**
 * Formats values for use inside a shell command, escaped as a single argument
 * so that a variable value can never break out of its place in the command.
 */
final class ShellArgumentValueFormatter implements ValueFormatterInterface
{
    private readonly VerbatimValueFormatter $verbatim;

    public function __construct()
    {
        $this->verbatim = new VerbatimValueFormatter();
    }

    public function format(mixed $value): string
    {
        return escapeshellarg($this->verbatim->format($value));
    }
}
