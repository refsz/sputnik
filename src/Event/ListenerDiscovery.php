<?php

declare(strict_types=1);

namespace Sputnik\Event;

use Sputnik\Attribute\AsListener;
use Sputnik\Support\PhpFileParser;

final class ListenerDiscovery
{
    /**
     * @var array<ListenerMetadata>
     */
    private array $listeners = [];

    private bool $discovered = false;

    /**
     * @param array<string> $directories Directories to scan for listeners
     */
    public function __construct(
        private readonly array $directories,
    ) {
    }

    /**
     * Create an instance pre-populated with already-discovered metadata,
     * bypassing filesystem scanning entirely.
     *
     * @param array<ListenerMetadata> $listeners
     */
    public static function withPreloadedData(array $listeners): self
    {
        $instance = new self([]);
        $instance->listeners = $listeners;
        $instance->discovered = true;

        return $instance;
    }

    /**
     * Discover all listeners from configured directories.
     *
     * @return array<ListenerMetadata>
     */
    public function discoverAll(): array
    {
        if ($this->discovered) {
            return $this->listeners;
        }

        foreach ($this->directories as $directory) {
            $this->scanDirectory($directory);
        }

        // Sort by priority (higher first)
        usort($this->listeners, static fn (ListenerMetadata $a, ListenerMetadata $b): int => $b->priority <=> $a->priority);

        $this->discovered = true;

        return $this->listeners;
    }

    /**
     * Get listeners for a specific event.
     *
     * @return array<ListenerMetadata>
     */
    public function getListenersForEvent(string $eventClass): array
    {
        $this->discoverAll();

        return array_filter(
            $this->listeners,
            static fn (ListenerMetadata $listener): bool => $listener->event === $eventClass,
        );
    }

    private function scanDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $this->processFile($file->getPathname());
        }
    }

    private function processFile(string $filePath): void
    {
        $className = PhpFileParser::extractClassName($filePath);

        if ($className === null) {
            return;
        }

        // Ensure the file is loaded
        require_once $filePath;

        if (!class_exists($className)) {
            return;
        }

        $reflection = new \ReflectionClass($className);
        $attributes = $reflection->getAttributes(AsListener::class);

        // A class can have multiple AsListener attributes (IS_REPEATABLE)
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            $this->listeners[] = new ListenerMetadata(
                className: $className,
                event: $instance->event,
                priority: $instance->priority,
                environment: $instance->environment,
            );
        }
    }
}
