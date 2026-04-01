<?php

declare(strict_types=1);

namespace Sputnik\Console;

use Symfony\Component\Console\Output\OutputInterface;

final class SputnikOutput
{
    private int $currentStep = 0;

    private int $totalSteps = 0;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly string $version,
        private readonly string $configFile,
        private readonly string $contextName,
    ) {
    }

    public function header(): void
    {
        $this->output->writeln(
            \sprintf("\xF0\x9F\x9B\xB0  <fg=green;options=bold>Sputnik v%s</> <fg=gray>│</> %s <fg=gray>│</> %s", $this->version, $this->configFile, $this->contextName),
        );
        $this->output->writeln('');
    }

    public function taskStart(string $name, string $description): void
    {
        $this->output->writeln(
            \sprintf('<info>▸ %s</info> · %s', $name, $description),
        );
        $this->output->writeln('');
    }

    public function command(string $command): void
    {
        ++$this->currentStep;

        if ($this->totalSteps > 1) {
            $this->output->writeln(
                \sprintf('  (%d/%d) <comment>> %s</comment>', $this->currentStep, $this->totalSteps, $command),
            );
        } else {
            $this->output->writeln(
                \sprintf('  <comment>> %s</comment>', $command),
            );
        }
    }

    public function commandDone(float $duration, int $exitCode): void
    {
        // Skip per-command result for single-step tasks — the task-level success/failure is enough
        if ($this->totalSteps <= 1) {
            return;
        }

        if ($exitCode === 0) {
            $this->output->writeln(\sprintf('  <info>✓</info> <fg=gray>(%.2fs)</>', $duration));
        } else {
            $this->output->writeln(\sprintf('  <error>✗</error> <fg=gray>(%.2fs)</>', $duration));
        }

        $this->output->writeln('');
    }

    public function setTotalSteps(int $total): void
    {
        $this->totalSteps = $total;
    }

    public function resetSteps(): void
    {
        $this->currentStep = 0;
        $this->totalSteps = 0;
    }

    /**
     * @return array{int, int}
     */
    public function saveSteps(): array
    {
        return [$this->currentStep, $this->totalSteps];
    }

    /**
     * @param array{int, int} $state
     */
    public function restoreSteps(array $state): void
    {
        [$this->currentStep, $this->totalSteps] = $state;
    }

    public function success(?float $duration = null, ?string $message = null): void
    {
        $text = '<info>✓ Done</info>';
        if ($duration !== null) {
            $text .= \sprintf(' <fg=gray>(%.2fs)</>', $duration);
        }

        $this->output->writeln($text);

        if ($message !== null) {
            $this->output->writeln('  ' . $message);
        }
    }

    public function failure(string $message): void
    {
        $this->output->writeln(\sprintf('<error>✗ Failed:</error> %s', $message));
    }

    public function skipped(string $message): void
    {
        $this->output->writeln(\sprintf('<comment>⊘ Skipped:</comment> %s', $message));
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }
}
