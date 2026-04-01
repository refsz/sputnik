<?php

declare(strict_types=1);

namespace Sputnik\Tests\Functional\Command;

use Sputnik\Config\Configuration;
use Sputnik\Console\Command\ContextSwitchCommand;
use Sputnik\Context\ContextManager;
use Sputnik\Tests\Support\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ContextSwitchCommandTest extends TestCase
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

    public function testSwitchesToValidContext(): void
    {
        $config = new Configuration([
            'contexts' => ['local' => [], 'staging' => ['description' => 'Staging']],
            'defaults' => ['context' => 'local'],
        ]);

        $command = new ContextSwitchCommand(new ContextManager($config, $this->tempDir), new EventDispatcher());
        $tester = new CommandTester($command);
        $tester->execute(['context' => 'staging']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('staging', $tester->getDisplay());
    }

    public function testInvalidContextShowsError(): void
    {
        $config = new Configuration([
            'contexts' => ['local' => []],
            'defaults' => ['context' => 'local'],
        ]);

        $command = new ContextSwitchCommand(new ContextManager($config, $this->tempDir), new EventDispatcher());
        $tester = new CommandTester($command);
        $tester->execute(['context' => 'nonexistent']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testSwitchingToCurrentContextShowsNote(): void
    {
        $config = new Configuration([
            'contexts' => ['local' => [], 'staging' => []],
            'defaults' => ['context' => 'local'],
        ]);

        $manager = new ContextManager($config, $this->tempDir);
        // First switch to staging so current context is staging
        $manager->switchTo('staging');

        $command = new ContextSwitchCommand($manager, new EventDispatcher());
        $tester = new CommandTester($command);
        $tester->execute(['context' => 'staging']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Already in context', $tester->getDisplay());
    }

    public function testSwitchShowsContextDescriptionWhenAvailable(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
                'staging' => ['description' => 'The staging server'],
            ],
            'defaults' => ['context' => 'local'],
        ]);

        $command = new ContextSwitchCommand(new ContextManager($config, $this->tempDir), new EventDispatcher());
        $tester = new CommandTester($command);
        $tester->execute(['context' => 'staging']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('The staging server', $tester->getDisplay());
    }

    public function testInvalidContextShowsAvailableContextsWithDescriptions(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => ['description' => 'Local dev'],
                'prod' => ['description' => 'Production'],
            ],
            'defaults' => ['context' => 'local'],
        ]);

        $command = new ContextSwitchCommand(new ContextManager($config, $this->tempDir), new EventDispatcher());
        $tester = new CommandTester($command);
        $tester->execute(['context' => 'nonexistent']);

        $this->assertSame(1, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('local', $display);
        $this->assertStringContainsString('prod', $display);
    }
}
