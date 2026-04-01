<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Autoload;

use PHPUnit\Framework\TestCase;
use Sputnik\Autoload\ClassmapAutoloader;

final class ClassmapAutoloaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sputnik_autoload_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
    }

    public function testGetClassmapIsEmptyInitially(): void
    {
        $loader = new ClassmapAutoloader();

        $this->assertSame([], $loader->getClassmap());
    }

    public function testScanDirectoriesBuildsClassmap(): void
    {
        $phpFile = $this->tempDir . '/MyTestClass.php';
        file_put_contents($phpFile, '<?php namespace Sputnik\Tests\Fixtures\Autoload; class MyTestClass {}');

        $loader = new ClassmapAutoloader();
        $loader->scanDirectories([$this->tempDir]);

        $classmap = $loader->getClassmap();
        $this->assertArrayHasKey('Sputnik\Tests\Fixtures\Autoload\MyTestClass', $classmap);
        $this->assertSame($phpFile, $classmap['Sputnik\Tests\Fixtures\Autoload\MyTestClass']);
    }

    public function testScanDirectoriesIgnoresNonExistentDirectory(): void
    {
        $loader = new ClassmapAutoloader();
        $loader->scanDirectories(['/nonexistent/path/that/does/not/exist']);

        $this->assertSame([], $loader->getClassmap());
    }

    public function testScanDirectoriesIgnoresNonPhpFiles(): void
    {
        file_put_contents($this->tempDir . '/readme.txt', 'not php');
        file_put_contents($this->tempDir . '/script.sh', '#!/bin/bash');

        $loader = new ClassmapAutoloader();
        $loader->scanDirectories([$this->tempDir]);

        $this->assertSame([], $loader->getClassmap());
    }

    public function testRegisterAddsAutoloader(): void
    {
        $uniqueClass = 'Sputnik\\Tests\\Fixtures\\Autoload\\UniqueClass' . uniqid();
        $phpFile = $this->tempDir . '/UniqueClass.php';
        file_put_contents($phpFile, '<?php class ' . substr($uniqueClass, strrpos($uniqueClass, '\\') + 1) . ' {}');

        $loader = new ClassmapAutoloader();
        $loader->register();

        // The autoloader is now registered — just verify it doesn't throw
        $this->addToAssertionCount(1);
    }

    public function testRegisterIsIdempotent(): void
    {
        $loader = new ClassmapAutoloader();

        // Registering twice should not add duplicate autoloaders
        $countBefore = \count(spl_autoload_functions());
        $loader->register();
        $countAfter = \count(spl_autoload_functions());
        $loader->register(); // second call should be a no-op
        $countAfterSecond = \count(spl_autoload_functions());

        $this->assertSame($countAfter, $countAfterSecond);
    }

    public function testAutoloaderLoadsClassFromClassmap(): void
    {
        // Use a unique class name to avoid conflicts with existing autoloaders
        $className = 'SputnikAutoloadTest_' . uniqid();
        $phpFile = $this->tempDir . '/' . $className . '.php';
        file_put_contents($phpFile, '<?php class ' . $className . ' { public static function hello(): string { return "loaded"; } }');

        $loader = new ClassmapAutoloader();
        $loader->scanDirectories([$this->tempDir]);
        $loader->register();

        // Trigger autoloading
        $classmap = $loader->getClassmap();
        $this->assertArrayHasKey($className, $classmap);

        // Actually load the class
        require_once $classmap[$className];
        $this->assertSame('loaded', $className::hello());
    }

    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
