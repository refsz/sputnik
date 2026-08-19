<?php

declare(strict_types=1);

namespace Sputnik\Tests\Functional\Command;

use Sputnik\Console\Command\InitCommand;
use Sputnik\Tests\Support\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InitCommandTest extends TestCase
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

    public function testInitCreatesConfigAndTask(): void
    {
        $tester = $this->tester();
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($this->tempDir . '/.sputnik.dist.neon');
        $this->assertFileExists($this->tempDir . '/sputnik/ExampleTask.php');
    }

    public function testInitWritesToItsTargetDirectoryNotTheCurrentOne(): void
    {
        // --working-dir has to reach the files, so run from a directory that is
        // demonstrably not the target and must stay empty.
        $elsewhere = $this->createTempDir();
        $previous = getcwd();

        if ($previous === false) {
            self::fail('Could not determine the current directory');
        }

        chdir($elsewhere);

        try {
            $tester = new CommandTester(new InitCommand($this->tempDir));
            $tester->execute([]);

            $this->assertFileExists($this->tempDir . '/.sputnik.dist.neon');
            $this->assertFileExists($this->tempDir . '/sputnik/ExampleTask.php');
            $this->assertSame(['.', '..'], scandir($elsewhere));
        } finally {
            chdir($previous);
            $this->removeTempDir($elsewhere);
        }
    }

    public function testInitSkipsExistingFiles(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', 'existing');

        $tester = $this->tester();
        $tester->execute([]);

        $this->assertSame('existing', file_get_contents($this->tempDir . '/.sputnik.dist.neon'));
        $this->assertStringContainsString('Skipped', $tester->getDisplay());
    }

    public function testInitForceOverwritesFiles(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', 'old');

        $tester = $this->tester();
        $tester->execute(['--force' => true]);

        $this->assertNotSame('old', file_get_contents($this->tempDir . '/.sputnik.dist.neon'));
    }

    public function testInitSkipsExistingExampleTask(): void
    {
        mkdir($this->tempDir . '/sputnik', 0755, true);
        file_put_contents($this->tempDir . '/sputnik/ExampleTask.php', '<?php // existing');

        $tester = $this->tester();
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame('<?php // existing', file_get_contents($this->tempDir . '/sputnik/ExampleTask.php'));
        $this->assertStringContainsString('Skipped', $tester->getDisplay());
    }

    public function testInitCreatesAllFilesAndReportsCreated(): void
    {
        $tester = $this->tester();
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Created', $display);
        $this->assertStringContainsString('Next steps', $display);
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new InitCommand($this->tempDir));
    }
}
