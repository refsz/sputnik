<?php

declare(strict_types=1);

namespace Sputnik\Variable;

use Symfony\Component\Process\Process;

final class DynamicVariableResolver
{
    /**
     * Resolve a dynamic variable definition.
     *
     * @param array<string, mixed> $definition
     */
    public function resolve(array $definition): mixed
    {
        $type = $definition['type'] ?? 'command';

        return match ($type) {
            'git' => $this->resolveGit($definition),
            'command' => $this->resolveCommand($definition),
            'script' => $this->resolveScript($definition),
            'system' => $this->resolveSystem($definition),
            'composite' => $this->resolveComposite($definition),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function resolveGit(array $definition): ?string
    {
        $property = $definition['property'] ?? 'branch';

        return match ($property) {
            'branch' => $this->runProcess(['git', 'rev-parse', '--abbrev-ref', 'HEAD']),
            'commit' => $this->runProcess(['git', 'rev-parse', 'HEAD']),
            'commitShort' => $this->runProcess(['git', 'rev-parse', '--short', 'HEAD']),
            'tag' => $this->runProcess(['git', 'describe', '--tags', '--abbrev=0']),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function resolveCommand(array $definition): ?string
    {
        $command = $definition['command'] ?? null;
        if ($command === null) {
            return null;
        }

        return $this->runShellCommand($command);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function resolveScript(array $definition): ?string
    {
        $script = $definition['script'] ?? null;
        if ($script === null) {
            return null;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'sputnik_script_');
        if ($tempFile === false) {
            return null;
        }

        try {
            file_put_contents($tempFile, $script);
            chmod($tempFile, 0755);

            return $this->runProcess([$tempFile]);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function resolveSystem(array $definition): mixed
    {
        $property = $definition['property'] ?? null;

        return match ($property) {
            'hostname' => gethostname(),
            'user' => get_current_user(),
            'os' => \PHP_OS_FAMILY,
            'phpVersion' => \PHP_VERSION,
            'cwd' => getcwd(),
            'timestamp' => time(),
            'date' => date('Y-m-d'),
            'datetime' => date('Y-m-d H:i:s'),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    private function resolveComposite(array $definition): array
    {
        $providers = $definition['providers'] ?? [];
        $result = [];

        foreach ($providers as $name => $providerDefinition) {
            $result[$name] = $this->resolve($providerDefinition);
        }

        return $result;
    }

    /**
     * Run a process with array of arguments (safer, no shell).
     *
     * @param array<string> $command
     */
    private function runProcess(array $command): ?string
    {
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }

    /**
     * Run a shell command (for user-defined commands that need shell features).
     */
    private function runShellCommand(string $command): ?string
    {
        $process = Process::fromShellCommandline($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }
}
