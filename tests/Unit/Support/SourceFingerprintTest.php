<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Support;

use Sputnik\Support\SourceFingerprint;
use Sputnik\Tests\Support\TestCase;

final class SourceFingerprintTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = $this->createTempDir();
        file_put_contents($this->dir . '/One.php', '<?php class One {}');
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->dir);
        parent::tearDown();
    }

    public function testUnchangedDirectoryKeepsItsFingerprint(): void
    {
        $this->assertSame(
            SourceFingerprint::ofDirectory($this->dir),
            SourceFingerprint::ofDirectory($this->dir),
        );
    }

    public function testAddedFileChangesTheFingerprint(): void
    {
        $before = SourceFingerprint::ofDirectory($this->dir);

        file_put_contents($this->dir . '/Two.php', '<?php class Two {}');

        $this->assertNotSame($before, SourceFingerprint::ofDirectory($this->dir));
    }

    public function testTouchedFileChangesTheFingerprint(): void
    {
        $before = SourceFingerprint::ofDirectory($this->dir);

        touch($this->dir . '/One.php', time() + 60);
        clearstatcache(true, $this->dir . '/One.php');

        $this->assertNotSame($before, SourceFingerprint::ofDirectory($this->dir));
    }

    public function testNestedFileIsIncluded(): void
    {
        $before = SourceFingerprint::ofDirectory($this->dir);

        mkdir($this->dir . '/Nested');
        file_put_contents($this->dir . '/Nested/Three.php', '<?php class Three {}');

        $this->assertNotSame($before, SourceFingerprint::ofDirectory($this->dir));
    }

    public function testNonPhpFileIsIgnored(): void
    {
        $before = SourceFingerprint::ofDirectory($this->dir);

        file_put_contents($this->dir . '/notes.md', 'irrelevant');

        $this->assertSame($before, SourceFingerprint::ofDirectory($this->dir));
    }

    public function testMissingDirectoryIsStableRatherThanFatal(): void
    {
        $missing = $this->dir . '/does-not-exist';

        $this->assertSame(
            SourceFingerprint::ofDirectory($missing),
            SourceFingerprint::ofDirectory($missing),
        );
    }
}
