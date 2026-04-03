<?php

declare(strict_types=1);

namespace Sputnik\Task;

use Sputnik\Attribute\Argument;
use Sputnik\Attribute\Option;
use Sputnik\Attribute\Task;
use Sputnik\Support\PhpFileParser;

final class TaskDiscovery
{
    private const RESERVED_NAMES = [
        'init',
        'run',
        'list',
        'help',
        'completion',
        'context:switch',
        'context:list',
    ];

    /**
     * @var array<string, TaskMetadata>
     */
    private array $tasks = [];

    /**
     * @var array<string, string>
     */
    private array $aliasMap = [];

    private bool $discovered = false;

    /**
     * @param array<string>      $directories Directories to scan for tasks
     * @param list<class-string> $classes     Explicit task classes to register
     */
    public function __construct(
        private readonly array $directories,
        private readonly array $classes = [],
    ) {
    }

    /**
     * Create an instance pre-populated with already-discovered metadata,
     * bypassing filesystem scanning entirely.
     *
     * @param array<string, TaskMetadata> $tasks
     * @param array<string, string>       $aliasMap
     */
    public static function withPreloadedData(array $tasks, array $aliasMap): self
    {
        $instance = new self([]);
        $instance->tasks = $tasks;
        $instance->aliasMap = $aliasMap;
        $instance->discovered = true;

        return $instance;
    }

    /**
     * Discover all tasks from configured directories.
     *
     * @return array<string, TaskMetadata>
     */
    public function discoverAll(): array
    {
        if ($this->discovered) {
            return $this->tasks;
        }

        foreach ($this->directories as $directory) {
            $this->scanDirectory($directory);
        }

        foreach ($this->classes as $className) {
            $this->processClass($className);
        }

        $this->discovered = true;

        return $this->tasks;
    }

    /**
     * Get a task by name or alias.
     */
    public function getTask(string $nameOrAlias): ?TaskMetadata
    {
        $this->discoverAll();

        // Direct name lookup
        if (isset($this->tasks[$nameOrAlias])) {
            return $this->tasks[$nameOrAlias];
        }

        // Alias lookup
        if (isset($this->aliasMap[$nameOrAlias])) {
            return $this->tasks[$this->aliasMap[$nameOrAlias]];
        }

        return null;
    }

    /**
     * Check if a task exists.
     */
    public function hasTask(string $nameOrAlias): bool
    {
        return $this->getTask($nameOrAlias) instanceof TaskMetadata;
    }

    /**
     * Get all task names (excluding aliases).
     *
     * @return array<string>
     */
    public function getTaskNames(): array
    {
        $this->discoverAll();

        return array_keys($this->tasks);
    }

    /**
     * Return the alias-to-canonical-name map (populated after discoverAll()).
     *
     * @return array<string, string>
     */
    public function getAliasMap(): array
    {
        $this->discoverAll();

        return $this->aliasMap;
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

        $this->processClass($className);
    }

    /**
     * @param class-string $className
     */
    private function processClass(string $className): void
    {
        $reflection = new \ReflectionClass($className);
        $taskAttributes = $reflection->getAttributes(Task::class);

        if ($taskAttributes === []) {
            return;
        }

        $taskAttribute = $taskAttributes[0]->newInstance();
        $options = $this->extractOptions($reflection);
        $arguments = $this->extractArguments($reflection);

        $metadata = new TaskMetadata(
            className: $className,
            attribute: $taskAttribute,
            options: $options,
            arguments: $arguments,
        );

        if (\in_array($taskAttribute->name, self::RESERVED_NAMES, true)) {
            throw new TaskDiscoveryException(\sprintf(
                "Task name '%s' is reserved by a built-in command",
                $taskAttribute->name,
            ));
        }

        if (isset($this->tasks[$taskAttribute->name])) {
            throw new TaskDiscoveryException(\sprintf(
                "Duplicate task name '%s': defined in both '%s' and '%s'",
                $taskAttribute->name,
                $this->tasks[$taskAttribute->name]->className,
                $className,
            ));
        }

        if (isset($this->aliasMap[$taskAttribute->name])) {
            throw new TaskDiscoveryException(\sprintf(
                "Task name '%s' collides with alias defined by task '%s'",
                $taskAttribute->name,
                $this->aliasMap[$taskAttribute->name],
            ));
        }

        $this->tasks[$taskAttribute->name] = $metadata;

        // Register aliases
        foreach ($taskAttribute->aliases as $alias) {
            if (\in_array($alias, self::RESERVED_NAMES, true)) {
                throw new TaskDiscoveryException(\sprintf(
                    "Alias '%s' on task '%s' is reserved by a built-in command",
                    $alias,
                    $taskAttribute->name,
                ));
            }

            if (isset($this->tasks[$alias])) {
                throw new TaskDiscoveryException(\sprintf(
                    "Alias '%s' on task '%s' collides with existing task name '%s'",
                    $alias,
                    $taskAttribute->name,
                    $alias,
                ));
            }

            if (isset($this->aliasMap[$alias])) {
                throw new TaskDiscoveryException(\sprintf(
                    "Duplicate alias '%s': defined by both '%s' and '%s'",
                    $alias,
                    $this->aliasMap[$alias],
                    $taskAttribute->name,
                ));
            }

            $this->aliasMap[$alias] = $taskAttribute->name;
        }
    }

    /**
     * Extract Option attributes from class properties.
     *
     * @param \ReflectionClass<object> $reflection
     *
     * @return array<Option>
     */
    private function extractOptions(\ReflectionClass $reflection): array
    {
        $options = [];

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Option::class);
            foreach ($attributes as $attribute) {
                $options[] = $attribute->newInstance();
            }
        }

        return $options;
    }

    /**
     * Extract Argument attributes from class properties.
     *
     * @param \ReflectionClass<object> $reflection
     *
     * @return array<Argument>
     */
    private function extractArguments(\ReflectionClass $reflection): array
    {
        $arguments = [];

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Argument::class);
            foreach ($attributes as $attribute) {
                $arguments[] = $attribute->newInstance();
            }
        }

        return $arguments;
    }
}
