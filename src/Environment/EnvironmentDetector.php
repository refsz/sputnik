<?php

declare(strict_types=1);

namespace Sputnik\Environment;

use Sputnik\Exception\RuntimeException;
use Symfony\Component\Process\Process;

final class EnvironmentDetector
{
    private const DEFAULT_SHELL = ['sh', '-c'];

    private bool $isContainer;

    /**
     * @param list<string>|null $executor Prepended to a command's argv, e.g. ['ddev', 'exec']
     * @param list<string>|null $shell    How to start a shell inside the executor, default ['sh', '-c']
     */
    public function __construct(
        private readonly ?string $detection = null,
        private readonly ?array $executor = null,
        private readonly ?array $shell = null,
    ) {
        $this->isContainer = $this->detect();
    }

    public function isContainer(): bool
    {
        return $this->isContainer;
    }

    /**
     * @return list<string>|null
     */
    public function getExecutor(): ?array
    {
        return $this->executor;
    }

    /**
     * Wrap a command for the target environment.
     *
     * An argv list is prepended with the executor, so argument boundaries
     * survive. A shell string becomes the final argument of a shell invocation
     * inside the executor - itself just argv, which is why no quoting is
     * needed and why one executor covers both forms.
     *
     * @param list<string>|string $command
     *
     * @return list<string>|string
     */
    public function wrapCommand(array|string $command, ?string $environment): array|string
    {
        if ($environment === 'host' && $this->isContainer) {
            throw new RuntimeException('Host task cannot be executed inside a container');
        }

        if ($environment === 'container' && !$this->isContainer && $this->executor === null) {
            throw new RuntimeException('Container task requires an environment.executor in the configuration');
        }

        if ($environment !== 'container' || $this->isContainer || $this->executor === null) {
            return $command;
        }

        if (\is_array($command)) {
            return [...$this->executor, ...$command];
        }

        return [...$this->executor, ...($this->shell ?? self::DEFAULT_SHELL), $command];
    }

    private function detect(): bool
    {
        if ($this->detection !== null) {
            $process = Process::fromShellCommandline($this->detection);
            $process->run();

            return $process->getExitCode() === 0;
        }

        return file_exists('/.dockerenv') || file_exists('/run/.containerenv');
    }
}
