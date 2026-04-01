<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Variable;

use PHPUnit\Framework\TestCase;
use Sputnik\Config\Configuration;
use Sputnik\Variable\VariableResolver;

final class VariableResolverTest extends TestCase
{
    public function testResolveConstant(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => [
                    'app_name' => 'MyApp',
                    'version' => '1.0.0',
                ],
            ],
        ]);

        $resolver = new VariableResolver($config);

        $this->assertSame('MyApp', $resolver->resolve('app_name'));
        $this->assertSame('1.0.0', $resolver->resolve('version'));
    }

    public function testResolveWithDefault(): void
    {
        $config = new Configuration([]);

        $resolver = new VariableResolver($config);

        $this->assertSame('default', $resolver->resolve('missing', 'default'));
        $this->assertNull($resolver->resolve('missing'));
    }

    public function testHas(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => [
                    'exists' => 'value',
                ],
            ],
        ]);

        $resolver = new VariableResolver($config);

        $this->assertTrue($resolver->has('exists'));
        $this->assertFalse($resolver->has('missing'));
    }

    public function testContextOverridesGlobal(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => [
                    'env' => 'default',
                    'app' => 'MyApp',
                ],
            ],
            'contexts' => [
                'production' => [
                    'variables' => [
                        'constants' => [
                            'env' => 'production',
                        ],
                    ],
                ],
            ],
        ]);

        $resolver = new VariableResolver($config, 'production');

        $this->assertSame('production', $resolver->resolve('env'));
        $this->assertSame('MyApp', $resolver->resolve('app'));
    }

    public function testContextVariableAdded(): void
    {
        $config = new Configuration([
            'contexts' => [
                'local' => ['description' => 'Local'],
            ],
        ]);

        $resolver = new VariableResolver($config, 'local');

        $this->assertSame('local', $resolver->resolve('context'));
    }

    public function testNestedVariables(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => [
                    'database' => [
                        'host' => 'localhost',
                        'port' => 3306,
                    ],
                ],
            ],
        ]);

        $resolver = new VariableResolver($config);

        $this->assertSame('localhost', $resolver->resolve('database.host'));
        $this->assertSame(3306, $resolver->resolve('database.port'));
    }

    public function testAll(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => [
                    'app' => 'MyApp',
                    'nested' => [
                        'value' => 'test',
                    ],
                ],
            ],
        ]);

        $resolver = new VariableResolver($config);
        $all = $resolver->all();

        $this->assertArrayHasKey('app', $all);
        $this->assertArrayHasKey('nested.value', $all);
        $this->assertSame('MyApp', $all['app']);
        $this->assertSame('test', $all['nested.value']);
    }

    public function testAllNested(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => [
                    'app' => 'MyApp',
                    'nested' => [
                        'value' => 'test',
                    ],
                ],
            ],
        ]);

        $resolver = new VariableResolver($config);
        $all = $resolver->allNested();

        $this->assertSame('MyApp', $all['app']);
        $this->assertSame(['value' => 'test'], $all['nested']);
    }

    public function testDynamicSystemVariable(): void
    {
        $config = new Configuration([
            'variables' => [
                'dynamics' => [
                    'hostname' => [
                        'type' => 'system',
                        'property' => 'hostname',
                    ],
                    'php_version' => [
                        'type' => 'system',
                        'property' => 'phpVersion',
                    ],
                ],
            ],
        ]);

        $resolver = new VariableResolver($config);

        $this->assertSame(gethostname(), $resolver->resolve('hostname'));
        $this->assertSame(\PHP_VERSION, $resolver->resolve('php_version'));
    }

    public function testDynamicCommandVariable(): void
    {
        $config = new Configuration([
            'variables' => [
                'dynamics' => [
                    'echo_test' => [
                        'type' => 'command',
                        'command' => 'echo "hello"',
                    ],
                ],
            ],
        ]);

        $resolver = new VariableResolver($config);

        $this->assertSame('hello', $resolver->resolve('echo_test'));
    }

    public function testWithoutContext(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => [
                    'app' => 'MyApp',
                ],
            ],
        ]);

        $resolver = new VariableResolver($config);

        $this->assertNull($resolver->resolve('context'));
        $this->assertSame('MyApp', $resolver->resolve('app'));
    }

    public function testWithOverrides(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => [
                    'app' => 'MyApp',
                    'env' => 'production',
                ],
            ],
        ]);

        $resolver = new VariableResolver($config);
        $overridden = $resolver->withOverrides([
            'env' => 'development',
            'custom' => 'value',
        ]);

        // Original unchanged
        $this->assertSame('production', $resolver->resolve('env'));
        $this->assertNull($resolver->resolve('custom'));

        // New resolver has overrides
        $this->assertSame('development', $overridden->resolve('env'));
        $this->assertSame('value', $overridden->resolve('custom'));
        $this->assertSame('MyApp', $overridden->resolve('app'));
    }

    public function testWithOverridesNestedMerge(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => [
                    'database' => [
                        'host' => 'localhost',
                        'port' => 3306,
                    ],
                ],
            ],
        ]);

        $resolver = new VariableResolver($config);
        $overridden = $resolver->withOverrides([
            'database' => [
                'host' => 'db.example.com',
            ],
        ]);

        // Nested override merges
        $this->assertSame('db.example.com', $overridden->resolve('database.host'));
        $this->assertSame(3306, $overridden->resolve('database.port'));
    }
}
