<?php

declare(strict_types=1);

namespace Sputnik\Console;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Where Sputnik writes, as a service.
 *
 * The console output only exists once a command runs, while the container is
 * built before that. This holds the destination so everything that writes -
 * tasks, listeners, the shell executor - shares one channel instead of each
 * receiving its own, or none at all. Empty until a command fills it, and an
 * empty channel writes nowhere rather than failing.
 */
final class OutputChannel
{
    private ?OutputInterface $output = null;

    private ?SputnikOutput $sputnikOutput = null;

    public function set(OutputInterface $output, ?SputnikOutput $sputnikOutput = null): void
    {
        $this->output = $output;
        $this->sputnikOutput = $sputnikOutput;
    }

    public function output(): ?OutputInterface
    {
        return $this->sputnikOutput?->getOutput() ?? $this->output;
    }

    public function sputnikOutput(): ?SputnikOutput
    {
        return $this->sputnikOutput;
    }

    public function writeln(string $message): void
    {
        $this->output()?->writeln($message);
    }

    public function write(string $message): void
    {
        $this->output()?->write($message);
    }

    public function success(string $message): void
    {
        $this->writeln('<info>' . $message . '</info>');
    }

    public function comment(string $message): void
    {
        $this->writeln('<comment>' . $message . '</comment>');
    }
}
