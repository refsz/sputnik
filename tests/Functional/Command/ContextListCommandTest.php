<?php

declare(strict_types=1);

namespace Sputnik\Tests\Functional\Command;

use Sputnik\Config\Configuration;
use Sputnik\Console\Command\ContextListCommand;
use Sputnik\Context\ContextManager;
use Sputnik\Tests\Support\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ContextListCommandTest extends TestCase
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

    public function testListsContextsWithCurrentMarked(): void
    {
        $config = new Configuration([
            'contexts' => ['local' => ['description' => 'Local dev'], 'staging' => []],
            'defaults' => ['context' => 'local'],
        ]);

        $command = new ContextListCommand(new ContextManager($config, $this->tempDir));
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('local', $tester->getDisplay());
        $this->assertStringContainsString('staging', $tester->getDisplay());
    }

    public function testNoContextsShowsWarning(): void
    {
        $config = new Configuration(['contexts' => [], 'defaults' => ['context' => 'local']]);

        $command = new ContextListCommand(new ContextManager($config, $this->tempDir));
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString('No contexts configured', $tester->getDisplay());
    }
}
