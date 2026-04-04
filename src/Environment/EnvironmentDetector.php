<?php

declare(strict_types=1);

namespace Sputnik\Environment;

use Sputnik\Exception\RuntimeException;
use Symfony\Component\Process\Process;

final class EnvironmentDetector
{
    private bool $isContainer;

    public function __construct(
        private readonly ?string $detection = null,
        private readonly ?string $executor = null,
    ) {
        $this->isContainer = $this->detect();
    }

    public function isContainer(): bool
    {
        return $this->isContainer;
    }

    public function getExecutor(): ?string
    {
        return $this->executor;
    }

    public function wrapCommand(string $command, ?string $environment): string
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

        // Use single replacement to avoid double-substitution if $command contains '{command}'
        $pos = strpos($this->executor, '{command}');
        if ($pos === false) {
            return $this->executor;
        }

        return substr($this->executor, 0, $pos) . $command . substr($this->executor, $pos + 9);
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
