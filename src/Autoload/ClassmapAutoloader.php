<?php

declare(strict_types=1);

namespace Sputnik\Autoload;

use Sputnik\Support\PhpFileParser;

/**
 * Simple classmap autoloader for user task directories.
 *
 * Scans configured directories for PHP files, extracts FQCNs via
 * PhpFileParser, and registers an SPL autoloader that maps class
 * names to file paths.
 */
final class ClassmapAutoloader
{
    /**
     * @var array<string, string> FQCN => file path
     */
    private array $classmap = [];

    private bool $registered = false;

    /**
     * Scan directories and build the classmap.
     *
     * @param array<string> $directories
     */
    public function scanDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            $this->scanDirectory($directory);
        }
    }

    /**
     * Register the autoloader with SPL.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        spl_autoload_register($this->loadClass(...));
        $this->registered = true;
    }

    /**
     * Get the classmap.
     *
     * @return array<string, string>
     */
    public function getClassmap(): array
    {
        return $this->classmap;
    }

    private function loadClass(string $className): void
    {
        if (isset($this->classmap[$className])) {
            require_once $this->classmap[$className];
        }
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

            $className = PhpFileParser::extractClassName($file->getPathname());
            if ($className !== null) {
                $this->classmap[$className] = $file->getPathname();
            }
        }
    }
}
