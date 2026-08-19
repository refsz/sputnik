<?php

declare(strict_types=1);

namespace Sputnik\Secret;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

/**
 * A console section that redacts before writing, so that
 * `SymfonyStyle::table()` (which routes through `ConsoleOutputInterface::section()`)
 * and any direct use of a section stay masked like every other write.
 *
 * `ConsoleSectionOutput::overwrite()` calls `$this->writeln()` internally, so
 * it is covered by the override here without needing its own override.
 */
final class RedactingConsoleSectionOutput extends ConsoleSectionOutput
{
    /**
     * @param resource               $stream
     * @param ConsoleSectionOutput[] $sections
     */
    public function __construct(
        $stream,
        array &$sections,
        int $verbosity,
        bool $decorated,
        OutputFormatterInterface $formatter,
        private readonly SecretRedactor $redactor,
    ) {
        parent::__construct($stream, $sections, $verbosity, $decorated, $formatter);
    }

    /**
     * @param string|iterable<mixed> $messages
     */
    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        parent::write($this->redactor->redactMessages($messages), $newline, $options);
    }

    /**
     * @param string|iterable<mixed> $messages
     */
    public function writeln(string|iterable $messages, int $options = 0): void
    {
        parent::writeln($this->redactor->redactMessages($messages), $options);
    }
}
