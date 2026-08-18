<?php

declare(strict_types=1);

namespace Sputnik\Secret;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class RedactingConsoleOutput extends RedactingOutput implements ConsoleOutputInterface
{
    public function __construct(
        private readonly ConsoleOutputInterface $console,
        SecretRedactor $redactor,
    ) {
        parent::__construct($console, $redactor);
    }

    public function getErrorOutput(): OutputInterface
    {
        return new RedactingOutput($this->console->getErrorOutput(), $this->redactor());
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        $this->console->setErrorOutput($error);
    }

    public function section(): ConsoleSectionOutput
    {
        return $this->console->section();
    }
}
