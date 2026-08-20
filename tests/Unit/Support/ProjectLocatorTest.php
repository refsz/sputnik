<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Support;

use Sputnik\Support\ProjectLocator;
use Sputnik\Tests\Support\TestCase;

final class ProjectLocatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = $this->createTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
        parent::tearDown();
    }

    public function testFindsTheDirectoryHoldingTheConfig(): void
    {
        touch($this->tempDir . '/.sputnik.dist.neon');

        $this->assertSame(realpath($this->tempDir), ProjectLocator::locate($this->tempDir));
    }

    public function testWalksUpFromASubdirectory(): void
    {
        touch($this->tempDir . '/.sputnik.dist.neon');
        $deep = $this->tempDir . '/htdocs/web/sites';
        mkdir($deep, 0755, true);

        $this->assertSame(realpath($this->tempDir), ProjectLocator::locate($deep));
    }

    public function testTheLocalOverrideAloneAlsoMarksAProject(): void
    {
        // .sputnik.neon without a committed dist file is a valid project.
        touch($this->tempDir . '/.sputnik.neon');

        $this->assertSame(realpath($this->tempDir), ProjectLocator::locate($this->tempDir));
    }

    public function testTheNearestConfigWins(): void
    {
        touch($this->tempDir . '/.sputnik.dist.neon');
        $inner = $this->tempDir . '/inner';
        mkdir($inner, 0755, true);
        touch($inner . '/.sputnik.dist.neon');
        mkdir($inner . '/deeper', 0755, true);

        $this->assertSame(realpath($inner), ProjectLocator::locate($inner . '/deeper'));
    }

    public function testNoConfigAnywhereMeansNoProject(): void
    {
        // Nothing may be persisted in this case, so null has to be an answer
        // rather than a fallback to the starting directory.
        $this->assertNull(ProjectLocator::locate($this->tempDir));
    }

    public function testAMissingDirectoryIsNotAProject(): void
    {
        $this->assertNull(ProjectLocator::locate($this->tempDir . '/does-not-exist'));
    }
}
