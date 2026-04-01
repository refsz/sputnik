<?php

declare(strict_types=1);

namespace Sputnik\Tests\Support;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function fixture(string $path): string
    {
        return __DIR__ . '/../Fixtures/' . $path;
    }

    protected function fixtureContent(string $path): string
    {
        $content = file_get_contents($this->fixture($path));
        if ($content === false) {
            throw new \RuntimeException("Could not read fixture: {$path}");
        }

        return $content;
    }

    protected function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/sputnik_test_' . uniqid();
        mkdir($dir, 0755, true);

        return $dir;
    }

    protected function removeTempDir(string $dir): void
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
