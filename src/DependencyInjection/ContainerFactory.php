<?php

declare(strict_types=1);

namespace Sputnik\DependencyInjection;

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Sputnik\Config\Configuration;
use Sputnik\Console\Application;
use Sputnik\Exception\RuntimeException as SputnikRuntimeException;
use Sputnik\Support\SourceFingerprint;

final class ContainerFactory
{
    private const CACHE_DIR = '.sputnik/cache';

    public function __construct(
        private readonly Configuration $config,
        private readonly ?string $projectDir,
        private readonly string $workingDir,
        private readonly string $contextName,
        private readonly bool $debugMode = false,
    ) {
    }

    public function create(): Container
    {
        $cacheDir = $this->cacheDir();

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
                $this->projectDir,
                $this->workingDir,
                $this->getTaskFilesHash(),
                Application::VERSION,
                $this->getServiceDefinitionsFingerprint(),
            ],
        );

        return new $containerClass();
    }

    /**
     * The compiled container belongs to the project. Without one there is
     * nothing to keep between runs, and writing beside the caller is how a
     * .sputnik directory appeared in every directory the binary was invoked
     * from - so it goes to the system temp directory instead, keyed like any
     * other build of this container.
     */
    private function cacheDir(): string
    {
        if ($this->projectDir !== null) {
            return $this->projectDir . '/' . self::CACHE_DIR;
        }

        return sys_get_temp_dir() . '/sputnik-cache-' . md5(Application::VERSION);
    }

    /**
     * Fingerprint Sputnik's own sources, so any change to the service graph or
     * to a wired class invalidates the cache even when Application::VERSION is
     * the unresolved `@package_version@` placeholder, which it is on every
     * non-PHAR run.
     */
    private function getServiceDefinitionsFingerprint(): string
    {
        return SourceFingerprint::ofDirectory(\dirname(__DIR__));
    }

    /**
     * Generate a hash based on task directory file modification times.
     * Changes to any PHP file in task directories will invalidate the cache.
     */
    private function getTaskFilesHash(): string
    {
        $files = [];
        $directories = $this->config->getTaskDirectories($this->projectDir ?? $this->workingDir);

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
                'projectDir' => $this->projectDir,
                'workingDir' => $this->workingDir,
                'contextName' => $this->contextName,
                'debug' => $this->debugMode,
            ],
        ]);

        // Add extensions
        $compiler->addExtension('sputnik', new SputnikExtension($this->config, $this->projectDir ?? $this->workingDir, $this->workingDir));
    }
}
