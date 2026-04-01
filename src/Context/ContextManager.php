<?php

declare(strict_types=1);

namespace Sputnik\Context;

use Sputnik\Config\Configuration;
use Sputnik\Exception\RuntimeException as SputnikRuntimeException;

final class ContextManager
{
    private const STATE_DIR = '.sputnik';

    private const STATE_FILE = 'state.json';

    private ?string $currentContext = null;

    public function __construct(
        private readonly Configuration $config,
        private readonly string $workingDir,
    ) {
    }

    /**
     * Get the current context name.
     */
    public function getCurrentContext(): string
    {
        if ($this->currentContext !== null) {
            return $this->currentContext;
        }

        // Try to load from persisted state
        $persisted = $this->loadPersistedContext();
        if ($persisted !== null && $this->isValidContext($persisted)) {
            $this->currentContext = $persisted;

            return $this->currentContext;
        }

        // Fall back to default
        $this->currentContext = $this->config->getDefaultContext();

        return $this->currentContext;
    }

    /**
     * Switch to a different context.
     *
     * @return array{previous: string, new: string}
     *
     * @throws ContextNotFoundException
     */
    public function switchTo(string $contextName): array
    {
        if (!$this->isValidContext($contextName)) {
            throw ContextNotFoundException::forContext($contextName, $this->getAvailableContexts());
        }

        $previous = $this->getCurrentContext();
        $this->currentContext = $contextName;
        $this->persistContext($contextName);

        return [
            'previous' => $previous,
            'new' => $contextName,
        ];
    }

    /**
     * Check if a context exists.
     */
    public function isValidContext(string $contextName): bool
    {
        $contexts = $this->config->getContexts();

        return isset($contexts[$contextName]);
    }

    /**
     * Get all available context names.
     *
     * @return array<string>
     */
    public function getAvailableContexts(): array
    {
        return array_keys($this->config->getContexts());
    }

    /**
     * Get context configuration.
     *
     * @return array<string, mixed>|null
     */
    public function getContextConfig(string $contextName): ?array
    {
        return $this->config->getContext($contextName);
    }

    /**
     * Get current context configuration.
     *
     * @return array<string, mixed>|null
     */
    public function getCurrentContextConfig(): ?array
    {
        return $this->getContextConfig($this->getCurrentContext());
    }

    /**
     * Get context description.
     */
    public function getContextDescription(string $contextName): ?string
    {
        $config = $this->getContextConfig($contextName);

        return $config['description'] ?? null;
    }

    /**
     * Get the state directory path.
     */
    public function getStateDir(): string
    {
        return $this->workingDir . '/' . self::STATE_DIR;
    }

    /**
     * Get the state file path.
     */
    public function getStateFilePath(): string
    {
        return $this->getStateDir() . '/' . self::STATE_FILE;
    }

    private function loadPersistedContext(): ?string
    {
        $path = $this->getStateFilePath();

        if (file_exists($path)) {
            $content = file_get_contents($path);
            if ($content === false) {
                return null;
            }

            $state = json_decode($content, true);
            if (\is_array($state) && isset($state['currentContext'])) {
                return $state['currentContext'];
            }

            return null;
        }

        return $this->migrateOldStateFile();
    }

    private function migrateOldStateFile(): ?string
    {
        $oldPath = $this->getStateDir() . '/context';

        if (!file_exists($oldPath)) {
            return null;
        }

        $content = file_get_contents($oldPath);
        if ($content === false) {
            return null;
        }

        $contextName = trim($content);
        if ($contextName === '') {
            return null;
        }

        $this->persistContext($contextName);
        unlink($oldPath);

        return $contextName;
    }

    private function persistContext(string $contextName): void
    {
        $dir = $this->getStateDir();

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new SputnikRuntimeException('Could not create state directory: ' . $dir);
        }

        $state = [
            'currentContext' => $contextName,
            'lastSwitched' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'version' => 1,
        ];

        $result = file_put_contents(
            $this->getStateFilePath(),
            json_encode($state, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n",
        );

        if ($result === false) {
            throw new SputnikRuntimeException('Could not write state file: ' . $this->getStateFilePath());
        }
    }
}
