<?php

declare(strict_types=1);

namespace Sputnik\DependencyInjection;

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Sputnik\Config\Configuration;
use Sputnik\Console\Application;
use Sputnik\Exception\RuntimeException as SputnikRuntimeException;

final class ContainerFactory
{
    private const CACHE_DIR = '.sputnik/cache';

    public function __construct(
        private readonly Configuration $config,
        private readonly string $workingDir,
        private readonly string $contextName,
        private readonly bool $debugMode = false,
    ) {
    }

    public function create(): Container
    {
        $cacheDir = $this->workingDir . '/' . self::CACHE_DIR;

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            throw new SputnikRuntimeException('Could not create cache directory: ' . $cacheDir);
        }

        $loader = new ContainerLoader($cacheDir, $this->debugMode);

        $containerClass = $loader->load(
            function (Compiler $compiler): ?string {
                $this->configureCompiler($compiler);

                return null;
            },
            [
                $this->config->all(),
                $this->contextName,
                $this->workingDir,
                $this->getTaskFilesHash(),
                Application::VERSION,
                $this->getServiceDefinitionsFingerprint(),
            ],
        );

        return new $containerClass();
    }

    /**
     * Fingerprint the service graph itself, so a change to what the container
     * wires up invalidates the cache even when Application::VERSION is the
     * unresolved `@package_version@` placeholder (true for any non-PHAR run).
     */
    private function getServiceDefinitionsFingerprint(): string
    {
        $path = __DIR__ . '/SputnikExtension.php';
        $hash = md5_file($path);

        if ($hash === false) {
            throw new SputnikRuntimeException('Could not read service definitions file: ' . $path);
        }

        return $hash;
    }

    /**
     * Generate a hash based on task directory file modification times.
     * Changes to any PHP file in task directories will invalidate the cache.
     */
    private function getTaskFilesHash(): string
    {
        $files = [];
        $directories = $this->config->getTaskDirectories($this->workingDir);

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname() . ':' . $file->getMTime();
                }
            }
        }

        sort($files);

        return md5(implode('|', $files));
    }

    private function configureCompiler(Compiler $compiler): void
    {
        // Add parameters
        $compiler->addConfig([
            'parameters' => [
                'workingDir' => $this->workingDir,
                'contextName' => $this->contextName,
                'debug' => $this->debugMode,
            ],
        ]);

        // Add extensions
        $compiler->addExtension('sputnik', new SputnikExtension($this->config, $this->workingDir));
    }
}
