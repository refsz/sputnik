<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Sputnik\Config\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testGetReturnsValueForExistingKey(): void
    {
        $config = new Configuration(['key' => 'value']);

        $this->assertSame('value', $config->get('key'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $config = new Configuration([]);

        $this->assertSame('default', $config->get('missing', 'default'));
    }

    public function testGetSupportsDotNotation(): void
    {
        $config = new Configuration([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);

        $this->assertSame('localhost', $config->get('database.host'));
        $this->assertSame(3306, $config->get('database.port'));
    }

    public function testGetReturnsDeeplyNestedValues(): void
    {
        $config = new Configuration([
            'a' => [
                'b' => [
                    'c' => [
                        'd' => 'deep',
                    ],
                ],
            ],
        ]);

        $this->assertSame('deep', $config->get('a.b.c.d'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $config = new Configuration(['key' => 'value']);

        $this->assertTrue($config->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $config = new Configuration([]);

        $this->assertFalse($config->has('missing'));
    }

    public function testHasSupportsDotNotation(): void
    {
        $config = new Configuration([
            'database' => ['host' => 'localhost'],
        ]);

        $this->assertTrue($config->has('database.host'));
        $this->assertFalse($config->has('database.port'));
    }

    public function testAllReturnsCompleteData(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $config = new Configuration($data);

        $this->assertSame($data, $config->all());
    }

    public function testGetNamespaces(): void
    {
        $config = new Configuration([
            'namespaces' => [
                'app' => ['dir' => 'sputnik/tasks'],
                'vendor' => ['dir' => 'vendor/tasks'],
            ],
        ]);

        $namespaces = $config->getNamespaces();

        $this->assertCount(2, $namespaces);
        $this->assertSame(['dir' => 'sputnik/tasks'], $namespaces['app']);
    }

    public function testGetTaskDirectories(): void
    {
        $tempDir = sys_get_temp_dir();
        $taskDir = $tempDir . '/sputnik_test_tasks_' . uniqid();
        mkdir($taskDir, 0755, true);

        try {
            $config = new Configuration([
                'namespaces' => [
                    'app' => ['dir' => basename($taskDir)],
                ],
            ]);

            $directories = $config->getTaskDirectories($tempDir);

            $this->assertCount(1, $directories);
            $this->assertSame($taskDir, $directories[0]);
        } finally {
            rmdir($taskDir);
        }
    }

    public function testGetContexts(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => ['description' => 'Local env'],
                'prod' => ['description' => 'Production'],
            ],
        ]);

        $contexts = $config->getContexts();

        $this->assertCount(2, $contexts);
        $this->assertArrayHasKey('local', $contexts);
    }

    public function testGetContext(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => ['description' => 'Local env'],
            ],
        ]);

        $this->assertSame(['description' => 'Local env'], $config->getContext('local'));
        $this->assertNull($config->getContext('missing'));
    }

    public function testGetConstants(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => [
                    'appEnv' => 'dev',
                    'debug' => true,
                ],
            ],
        ]);

        $constants = $config->getConstants();

        $this->assertSame('dev', $constants['appEnv']);
        $this->assertTrue($constants['debug']);
    }

    public function testGetDefaultContext(): void
    {
        $config = new Configuration([
            'defaults' => ['context' => 'production'],
        ]);

        $this->assertSame('production', $config->getDefaultContext());
    }

    public function testGetDefaultContextReturnsLocalByDefault(): void
    {
        $config = new Configuration([]);

        $this->assertSame('local', $config->getDefaultContext());
    }
}
