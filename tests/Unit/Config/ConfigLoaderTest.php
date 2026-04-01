<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Config;

use Sputnik\Config\ConfigLoader;
use Sputnik\Config\Exception\ConfigValidationException;
use Sputnik\Tests\Support\TestCase;

final class ConfigLoaderTest extends TestCase
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

    public function testLoadsBaseConfig(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', "variables:\n    constants:\n        foo: bar\n");

        $loader = new ConfigLoader($this->tempDir, validate: false);
        $config = $loader->load();

        $this->assertSame('bar', $config->get('variables.constants.foo'));
    }

    public function testMergesLocalOverrides(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', "variables:\n    constants:\n        foo: bar\n        baz: qux\n");
        file_put_contents($this->tempDir . '/.sputnik.neon', "variables:\n    constants:\n        foo: overridden\n");

        $loader = new ConfigLoader($this->tempDir, validate: false);
        $config = $loader->load();

        $this->assertSame('overridden', $config->get('variables.constants.foo'));
        $this->assertSame('qux', $config->get('variables.constants.baz'));
    }

    public function testDeepMergeBehavior(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', "contexts:\n    local:\n        description: Local\n        variables:\n            constants:\n                a: 1\n");
        file_put_contents($this->tempDir . '/.sputnik.neon', "contexts:\n    local:\n        variables:\n            constants:\n                b: 2\n");

        $loader = new ConfigLoader($this->tempDir, validate: false);
        $config = $loader->load();

        $this->assertSame('Local', $config->get('contexts.local.description'));
        $this->assertSame(1, $config->get('contexts.local.variables.constants.a'));
        $this->assertSame(2, $config->get('contexts.local.variables.constants.b'));
    }

    public function testHasConfigReturnsFalseWhenMissing(): void
    {
        $loader = new ConfigLoader($this->tempDir);
        $this->assertFalse($loader->hasConfig());
    }

    public function testHasConfigReturnsTrueWhenPresent(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', '');
        $loader = new ConfigLoader($this->tempDir);
        $this->assertTrue($loader->hasConfig());
    }

    public function testMissingLocalConfigIsIgnored(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', "variables:\n    constants:\n        foo: bar\n");

        $loader = new ConfigLoader($this->tempDir, validate: false);
        $config = $loader->load();

        $this->assertSame('bar', $config->get('variables.constants.foo'));
    }

    public function testValidationRunsByDefault(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', "contexts:\n    local:\n        description: Local\n");

        $loader = new ConfigLoader($this->tempDir);
        $config = $loader->load();

        $this->assertNotNull($config);
    }

    public function testValidationCanBeDisabled(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', "anything_goes: true\n");

        $loader = new ConfigLoader($this->tempDir, validate: false);
        $config = $loader->load();

        $this->assertTrue($config->get('anything_goes'));
    }

    public function testGetBasePath(): void
    {
        $loader = new ConfigLoader('/my/project');
        $this->assertSame('/my/project/.sputnik.dist.neon', $loader->getBasePath());
    }

    public function testGetLocalPath(): void
    {
        $loader = new ConfigLoader('/my/project');
        $this->assertSame('/my/project/.sputnik.neon', $loader->getLocalPath());
    }

    public function testNonArrayNeonReturnsEmptyConfig(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', '"just a string"');

        $loader = new ConfigLoader($this->tempDir, validate: false);
        $config = $loader->load();

        $this->assertSame([], $config->all());
    }

    public function testMergeWithDeeplyNestedArrays(): void
    {
        // Base has three levels; override adds a new key at the deepest level
        file_put_contents(
            $this->tempDir . '/.sputnik.dist.neon',
            "contexts:\n    local:\n        variables:\n            constants:\n                db_host: localhost\n                db_port: 3306\n",
        );
        file_put_contents(
            $this->tempDir . '/.sputnik.neon',
            "contexts:\n    local:\n        variables:\n            constants:\n                db_host: 127.0.0.1\n                db_name: myapp\n",
        );

        $loader = new ConfigLoader($this->tempDir, validate: false);
        $config = $loader->load();

        // Override wins for db_host
        $this->assertSame('127.0.0.1', $config->get('contexts.local.variables.constants.db_host'));
        // Base value preserved for db_port
        $this->assertSame(3306, $config->get('contexts.local.variables.constants.db_port'));
        // Override adds new key
        $this->assertSame('myapp', $config->get('contexts.local.variables.constants.db_name'));
    }

    public function testMergeOverridesScalarWithScalar(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', "defaults:\n    context: local\n");
        file_put_contents($this->tempDir . '/.sputnik.neon', "defaults:\n    context: staging\n");

        $loader = new ConfigLoader($this->tempDir, validate: false);
        $config = $loader->load();

        $this->assertSame('staging', $config->get('defaults.context'));
    }

    public function testLoadFileThrowsOnInvalidNeon(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', "bad: [unclosed\n");

        $loader = new ConfigLoader($this->tempDir, validate: false);

        $this->expectException(ConfigValidationException::class);
        $loader->load();
    }
}
