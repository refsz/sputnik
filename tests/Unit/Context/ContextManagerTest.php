<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Context;

use Sputnik\Config\Configuration;
use Sputnik\Context\ContextManager;
use Sputnik\Context\ContextNotFoundException;
use Sputnik\Tests\Support\TestCase;

final class ContextManagerTest extends TestCase
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

    public function testGetCurrentContextReturnsDefault(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => ['description' => 'Local'],
                'prod' => ['description' => 'Production'],
            ],
            'defaults' => ['context' => 'local'],
        ]);

        $manager = new ContextManager($config, $this->tempDir);

        $this->assertSame('local', $manager->getCurrentContext());
    }

    public function testGetCurrentContextReturnsPersistedContext(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
                'staging' => [],
            ],
        ]);

        // Pre-persist a context
        $stateDir = $this->tempDir . '/.sputnik';
        mkdir($stateDir, 0755, true);
        file_put_contents($stateDir . '/state.json', json_encode([
            'currentContext' => 'staging',
            'lastSwitched' => '2026-01-01T00:00:00+00:00',
            'version' => 1,
        ]));

        $manager = new ContextManager($config, $this->tempDir);

        $this->assertSame('staging', $manager->getCurrentContext());
    }

    public function testGetCurrentContextIgnoresInvalidPersistedContext(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
            ],
            'defaults' => ['context' => 'local'],
        ]);

        // Pre-persist an invalid context
        $stateDir = $this->tempDir . '/.sputnik';
        mkdir($stateDir, 0755, true);
        file_put_contents($stateDir . '/state.json', json_encode([
            'currentContext' => 'nonexistent',
            'lastSwitched' => '2026-01-01T00:00:00+00:00',
            'version' => 1,
        ]));

        $manager = new ContextManager($config, $this->tempDir);

        $this->assertSame('local', $manager->getCurrentContext());
    }

    public function testSwitchToValidContext(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
                'staging' => [],
                'prod' => [],
            ],
        ]);

        $manager = new ContextManager($config, $this->tempDir);
        $result = $manager->switchTo('staging');

        $this->assertSame('local', $result['previous']);
        $this->assertSame('staging', $result['new']);
        $this->assertSame('staging', $manager->getCurrentContext());
    }

    public function testSwitchToInvalidContextThrows(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
            ],
        ]);

        $manager = new ContextManager($config, $this->tempDir);

        $this->expectException(ContextNotFoundException::class);
        $this->expectExceptionMessage('nonexistent');

        $manager->switchTo('nonexistent');
    }

    public function testContextNotFoundExceptionIncludesAvailable(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
                'staging' => [],
            ],
        ]);

        $manager = new ContextManager($config, $this->tempDir);

        try {
            $manager->switchTo('invalid');
            $this->fail('Expected ContextNotFoundException');
        } catch (ContextNotFoundException $e) {
            $this->assertSame('invalid', $e->contextName);
            $this->assertContains('local', $e->available);
            $this->assertContains('staging', $e->available);
        }
    }

    public function testIsValidContext(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
                'prod' => [],
            ],
        ]);

        $manager = new ContextManager($config, $this->tempDir);

        $this->assertTrue($manager->isValidContext('local'));
        $this->assertTrue($manager->isValidContext('prod'));
        $this->assertFalse($manager->isValidContext('invalid'));
    }

    public function testGetAvailableContexts(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
                'staging' => [],
                'prod' => [],
            ],
        ]);

        $manager = new ContextManager($config, $this->tempDir);
        $contexts = $manager->getAvailableContexts();

        $this->assertCount(3, $contexts);
        $this->assertContains('local', $contexts);
        $this->assertContains('staging', $contexts);
        $this->assertContains('prod', $contexts);
    }

    public function testGetContextConfig(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [
                    'description' => 'Local development',
                    'variables' => ['debug' => true],
                ],
            ],
        ]);

        $manager = new ContextManager($config, $this->tempDir);
        $contextConfig = $manager->getContextConfig('local');

        $this->assertSame('Local development', $contextConfig['description']);
        $this->assertSame(['debug' => true], $contextConfig['variables']);
    }

    public function testGetContextConfigReturnsNullForInvalid(): void
    {
        $config = new Configuration([
            'contexts' => ['local' => []],
        ]);

        $manager = new ContextManager($config, $this->tempDir);

        $this->assertNull($manager->getContextConfig('nonexistent'));
    }

    public function testGetContextDescription(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => ['description' => 'Local dev environment'],
                'prod' => [],
            ],
        ]);

        $manager = new ContextManager($config, $this->tempDir);

        $this->assertSame('Local dev environment', $manager->getContextDescription('local'));
        $this->assertNull($manager->getContextDescription('prod'));
    }

    public function testGetStateDir(): void
    {
        $config = new Configuration([]);
        $manager = new ContextManager($config, '/my/project');

        $this->assertSame('/my/project/.sputnik', $manager->getStateDir());
    }

    public function testGetStateFilePath(): void
    {
        $config = new Configuration([]);
        $manager = new ContextManager($config, '/my/project');

        $this->assertSame('/my/project/.sputnik/state.json', $manager->getStateFilePath());
    }

    public function testSwitchToPersistsContextAsJson(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
                'prod' => [],
            ],
        ]);

        $manager = new ContextManager($config, $this->tempDir);
        $manager->switchTo('prod');

        $stateFile = $this->tempDir . '/.sputnik/state.json';
        $this->assertFileExists($stateFile);

        $state = json_decode(file_get_contents($stateFile), true);
        $this->assertSame('prod', $state['currentContext']);
        $this->assertSame(1, $state['version']);
        $this->assertArrayHasKey('lastSwitched', $state);
    }

    public function testGetCurrentContextReadsJsonState(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
                'staging' => [],
            ],
        ]);

        $stateDir = $this->tempDir . '/.sputnik';
        mkdir($stateDir, 0755, true);
        file_put_contents($stateDir . '/state.json', json_encode([
            'currentContext' => 'staging',
            'lastSwitched' => '2026-03-24T10:00:00+00:00',
            'version' => 1,
        ]));

        $manager = new ContextManager($config, $this->tempDir);
        $this->assertSame('staging', $manager->getCurrentContext());
    }

    public function testGetCurrentContextConfigReturnsCurrentContextConfig(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => ['description' => 'Local env'],
            ],
            'defaults' => ['context' => 'local'],
        ]);

        $manager = new ContextManager($config, $this->tempDir);
        $contextConfig = $manager->getCurrentContextConfig();

        $this->assertNotNull($contextConfig);
        $this->assertSame('Local env', $contextConfig['description']);
    }

    public function testMigrateOldStateFileWithEmptyContentReturnsNull(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
            ],
            'defaults' => ['context' => 'local'],
        ]);

        $stateDir = $this->tempDir . '/.sputnik';
        mkdir($stateDir, 0755, true);
        // Write empty content to old state file
        file_put_contents($stateDir . '/context', '   ');

        $manager = new ContextManager($config, $this->tempDir);

        // Should fall back to default context
        $this->assertSame('local', $manager->getCurrentContext());
    }

    public function testMigratesOldPlainTextStateToJson(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => [],
                'staging' => [],
            ],
        ]);

        $stateDir = $this->tempDir . '/.sputnik';
        mkdir($stateDir, 0755, true);
        file_put_contents($stateDir . '/context', 'staging');

        $manager = new ContextManager($config, $this->tempDir);

        $this->assertSame('staging', $manager->getCurrentContext());
        $this->assertFileExists($stateDir . '/state.json');
        $this->assertFileDoesNotExist($stateDir . '/context');

        $state = json_decode(file_get_contents($stateDir . '/state.json'), true);
        $this->assertSame('staging', $state['currentContext']);
    }
}
