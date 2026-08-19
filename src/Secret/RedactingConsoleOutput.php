<?php

declare(strict_types=1);

namespace Sputnik\Secret;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

final class RedactingConsoleOutput extends RedactingOutput implements ConsoleOutputInterface
{
    /**
     * Sections created through this decorator, passed by reference into each
     * new section so they coordinate their line-clearing among themselves,
     * exactly as ConsoleOutput::section() does for its own sections.
     *
     * @var ConsoleSectionOutput[]
     */
    private array $sections = [];

    public function __construct(
        private readonly ConsoleOutputInterface $console,
        SecretRedactor $redactor,
    ) {
        parent::__construct($console, $redactor);
    }

    #[\Override]
    public function getErrorOutput(): OutputInterface
    {
        return new RedactingOutput($this->console->getErrorOutput(), $this->redactor());
    }

    #[\Override]
    public function setErrorOutput(OutputInterface $error): void
    {
        $this->console->setErrorOutput($error);
    }

    #[\Override]
    public function section(): ConsoleSectionOutput
    {
        if (!$this->console instanceof StreamOutput) {
            // No stream to build a redacting section from. In practice
            // bin/sputnik always passes a real ConsoleOutput (a StreamOutput),
            // so this falls back to the inner, unredacted section only for a
            // ConsoleOutputInterface implementation Sputnik does not use.
            return $this->console->section();
        }

        return new RedactingConsoleSectionOutput(
            $this->console->getStream(),
            $this->sections,
            $this->console->getVerbosity(),
            $this->console->isDecorated(),
            $this->console->getFormatter(),
            $this->redactor(),
        );
    }
}
