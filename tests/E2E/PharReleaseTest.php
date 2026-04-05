<?php

declare(strict_types=1);

namespace Sputnik\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Tests against the built PHAR artifact.
 * Skipped automatically if build/sputnik.phar does not exist.
 * Run `php -d phar.readonly=0 vendor/bin/box compile` first.
 */
final class PharReleaseTest extends TestCase
{
    private static string $phar;

    private string $tempDir;

    public static function setUpBeforeClass(): void
    {
        self::$phar = \dirname(__DIR__, 2) . '/build/sputnik.phar';

        if (!file_exists(self::$phar)) {
            self::markTestSkipped('PHAR not built. Run box compile first.');
        }
    }

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sputnik_phar_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testPharShowsVersion(): void
    {
        $result = $this->phar(['--version']);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Sputnik', $result->getOutput());
    }

    public function testPharVersionIsNotPlaceholder(): void
    {
        $result = $this->phar(['--version']);

        $this->assertStringNotContainsString('@package_version@', $result->getOutput());
    }

    public function testPharListShowsCommands(): void
    {
        $result = $this->phar(['list']);

        $this->assertSame(0, $result->getExitCode());
        $output = $result->getOutput();
        $this->assertStringContainsString('init', $output);
        $this->assertStringContainsString('run', $output);
        $this->assertStringContainsString('context:switch', $output);
        $this->assertStringContainsString('context:list', $output);
    }

    public function testPharInitCreatesProject(): void
    {
        $result = $this->phar(['init'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertFileExists($this->tempDir . '/.sputnik.dist.neon');
        $this->assertFileExists($this->tempDir . '/sputnik/ExampleTask.php');
    }

    public function testPharRunsExampleTask(): void
    {
        $this->phar(['init'], $this->tempDir);

        $result = $this->phar(['example'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Hello', $result->getOutput());
    }

    public function testPharInitHelp(): void
    {
        $result = $this->phar(['init', '--help']);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('.sputnik.dist.neon', $result->getOutput());
    }

    public function testPharContextSwitchAndList(): void
    {
        $this->phar(['init'], $this->tempDir);

        $switchResult = $this->phar(['context:switch', 'staging'], $this->tempDir);
        $this->assertSame(0, $switchResult->getExitCode());

        $listResult = $this->phar(['context:list'], $this->tempDir);
        $this->assertSame(0, $listResult->getExitCode());
        $this->assertStringContainsString('staging', $listResult->getOutput());
    }

    public function testPharNoConfigShowsNoConfig(): void
    {
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir, 0755, true);

        $result = $this->phar(['list'], $emptyDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('no config', $result->getOutput());
    }

    /**
     * @param array<string> $args
     */
    private function phar(array $args, ?string $cwd = null): Process
    {
        $process = new Process(
            ['php', self::$phar, ...$args],
            $cwd,
            ['COLUMNS' => '120'],
        );
        $process->run();

        return $process;
    }

    private function removeDir(string $dir): void
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
