<?php

declare(strict_types=1);

namespace Sputnik\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class SputnikBinaryTest extends TestCase
{
    private static string $binary;

    private string $tempDir;

    public static function setUpBeforeClass(): void
    {
        self::$binary = \dirname(__DIR__, 2) . '/bin/sputnik';
    }

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sputnik_e2e_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testVersionOutput(): void
    {
        $result = $this->sputnik(['--version']);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Sputnik', $result->getOutput());
    }

    public function testListShowsHeader(): void
    {
        $result = $this->sputnik(['list']);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Sputnik', $result->getOutput());
        $this->assertStringContainsString('PHP', $result->getOutput());
    }

    public function testListShowsAvailableCommands(): void
    {
        $result = $this->sputnik(['list']);

        $output = $result->getOutput();
        $this->assertStringContainsString('init', $output);
        $this->assertStringContainsString('run', $output);
        $this->assertStringContainsString('context:switch', $output);
        $this->assertStringContainsString('context:list', $output);
    }

    public function testInitCreatesProjectStructure(): void
    {
        $result = $this->sputnik(['init'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertFileExists($this->tempDir . '/.sputnik.dist.neon');
        $this->assertFileExists($this->tempDir . '/sputnik/ExampleTask.php');
    }

    public function testInitAndRunExampleTask(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['example'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Hello', $result->getOutput());
    }

    public function testInitAndRunExampleTaskViaRunCommand(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['run', 'example'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Hello', $result->getOutput());
    }

    public function testRunNonExistentTaskFails(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['run', 'nonexistent'], $this->tempDir);

        $this->assertNotSame(0, $result->getExitCode());
    }

    public function testRuntimeVariableOverride(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['example', '-D', 'app_name=TestApp', '-v'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('TestApp', $result->getOutput());
    }

    public function testRuntimeVariableOverrideViaRunCommand(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['run', 'example', '-D', 'app_name=TestApp', '-v'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('TestApp', $result->getOutput());
    }

    public function testWorkingDirOption(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['--working-dir=' . $this->tempDir, 'example']);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Hello', $result->getOutput());
    }

    public function testContextSwitchAndList(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $listResult = $this->sputnik(['context:list'], $this->tempDir);
        $this->assertSame(0, $listResult->getExitCode());
        $this->assertStringContainsString('local', $listResult->getOutput());

        $switchResult = $this->sputnik(['context:switch', 'staging'], $this->tempDir);
        $this->assertSame(0, $switchResult->getExitCode());
    }

    public function testInitHelp(): void
    {
        $result = $this->sputnik(['init', '--help']);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('.sputnik.dist.neon', $result->getOutput());
    }

    public function testTaskOptionWithValue(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['example', '--name', 'Sputnik'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Sputnik', $result->getOutput());
    }

    public function testTaskOptionWithValueViaRunCommand(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['run', 'example', '--', '--name', 'Sputnik'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Sputnik', $result->getOutput());
    }

    public function testNoConfigShowsNoConfigInHeader(): void
    {
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir, 0755, true);

        $result = $this->sputnik(['list'], $emptyDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('no config', $result->getOutput());
    }

    public function testWithConfigShowsConfigInHeader(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['list'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('.sputnik.dist.neon', $result->getOutput());
    }

    public function testBothConfigsShownInHeader(): void
    {
        $this->sputnik(['init'], $this->tempDir);
        file_put_contents($this->tempDir . '/.sputnik.neon', '# local overrides');

        $result = $this->sputnik(['list'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('.sputnik.dist.neon + .sputnik.neon', $result->getOutput());
    }

    public function testOnlyLocalConfigShownInHeader(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.neon', "tasks:\n    directories:\n        - sputnik");

        $result = $this->sputnik(['list'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('.sputnik.neon', $result->getOutput());
        $this->assertStringNotContainsString('.sputnik.dist.neon', $result->getOutput());
    }

    /**
     * @param array<string> $args
     */
    private function sputnik(array $args, ?string $cwd = null): Process
    {
        $process = new Process(
            ['php', self::$binary, ...$args],
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
