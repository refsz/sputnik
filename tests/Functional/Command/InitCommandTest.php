<?php

declare(strict_types=1);

namespace Sputnik\Tests\Functional\Command;

use Sputnik\Console\Command\InitCommand;
use Sputnik\Tests\Support\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class InitCommandTest extends TestCase
{
    private string $tempDir;
    private string $originalDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = $this->createTempDir();
        $this->originalDir = getcwd();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        $this->removeTempDir($this->tempDir);
        parent::tearDown();
    }

    public function testInitCreatesConfigAndTask(): void
    {
        $tester = new CommandTester(new InitCommand());
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($this->tempDir . '/.sputnik.dist.neon');
        $this->assertFileExists($this->tempDir . '/sputnik/ExampleTask.php');
    }

    public function testInitSkipsExistingFiles(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', 'existing');

        $tester = new CommandTester(new InitCommand());
        $tester->execute([]);

        $this->assertSame('existing', file_get_contents($this->tempDir . '/.sputnik.dist.neon'));
        $this->assertStringContainsString('Skipped', $tester->getDisplay());
    }

    public function testInitForceOverwritesFiles(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', 'old');

        $tester = new CommandTester(new InitCommand());
        $tester->execute(['--force' => true]);

        $this->assertNotSame('old', file_get_contents($this->tempDir . '/.sputnik.dist.neon'));
    }

    public function testInitSkipsExistingExampleTask(): void
    {
        // Create tasks dir and existing task file
        mkdir($this->tempDir . '/tasks', 0755, true);
        file_put_contents($this->tempDir . '/sputnik/ExampleTask.php', '<?php // existing');

        $tester = new CommandTester(new InitCommand());
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        // Existing task should not be overwritten
        $this->assertSame('<?php // existing', file_get_contents($this->tempDir . '/sputnik/ExampleTask.php'));
        $this->assertStringContainsString('Skipped', $tester->getDisplay());
    }

    public function testInitCreatesAllFilesAndReportsCreated(): void
    {
        $tester = new CommandTester(new InitCommand());
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Created', $display);
        $this->assertStringContainsString('Next steps', $display);
    }
}
