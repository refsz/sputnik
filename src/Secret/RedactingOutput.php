<?php

declare(strict_types=1);

namespace Sputnik\Secret;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RedactingOutput implements OutputInterface
{
    public function __construct(
        private readonly OutputInterface $inner,
        private readonly SecretRedactor $redactor,
    ) {
    }

    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        $this->inner->write($this->redactMessages($messages), $newline, $options);
    }

    public function writeln(string|iterable $messages, int $options = 0): void
    {
        $this->inner->writeln($this->redactMessages($messages), $options);
    }

    public function setVerbosity(int $level): void
    {
        $this->inner->setVerbosity($level);
    }

    public function getVerbosity(): int
    {
        return $this->inner->getVerbosity();
    }

    public function isQuiet(): bool
    {
        return $this->inner->isQuiet();
    }

    public function isVerbose(): bool
    {
        return $this->inner->isVerbose();
    }

    public function isVeryVerbose(): bool
    {
        return $this->inner->isVeryVerbose();
    }

    public function isDebug(): bool
    {
        return $this->inner->isDebug();
    }

    public function setDecorated(bool $decorated): void
    {
        $this->inner->setDecorated($decorated);
    }

    public function isDecorated(): bool
    {
        return $this->inner->isDecorated();
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->inner->setFormatter($formatter);
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return $this->inner->getFormatter();
    }

    protected function redactor(): SecretRedactor
    {
        return $this->redactor;
    }

    /**
     * @param string|iterable<string> $messages
     *
     * @return string|list<string>
     */
    private function redactMessages(string|iterable $messages): string|array
    {
        if (\is_string($messages)) {
            return $this->redactor->redact($messages);
        }

        $redacted = [];
        foreach ($messages as $message) {
            $redacted[] = $this->redactor->redact($message);
        }

        return $redacted;
    }
}
