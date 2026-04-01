<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Variable;

use PHPUnit\Framework\TestCase;
use Sputnik\Variable\DynamicVariableResolver;

final class DynamicVariableResolverTest extends TestCase
{
    private DynamicVariableResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DynamicVariableResolver();
    }

    public function testResolveSystemHostname(): void
    {
        $result = $this->resolver->resolve(['type' => 'system', 'property' => 'hostname']);
        $this->assertSame(gethostname(), $result);
    }

    public function testResolveSystemPhpVersion(): void
    {
        $result = $this->resolver->resolve(['type' => 'system', 'property' => 'phpVersion']);
        $this->assertSame(\PHP_VERSION, $result);
    }

    public function testResolveSystemDate(): void
    {
        $result = $this->resolver->resolve(['type' => 'system', 'property' => 'date']);
        $this->assertSame(date('Y-m-d'), $result);
    }

    public function testResolveSystemUnknownProperty(): void
    {
        $result = $this->resolver->resolve(['type' => 'system', 'property' => 'nonexistent']);
        $this->assertNull($result);
    }

    public function testResolveCommand(): void
    {
        $result = $this->resolver->resolve(['type' => 'command', 'command' => 'echo hello']);
        $this->assertSame('hello', $result);
    }

    public function testResolveCommandNoCommand(): void
    {
        $result = $this->resolver->resolve(['type' => 'command']);
        $this->assertNull($result);
    }

    public function testResolveComposite(): void
    {
        $result = $this->resolver->resolve([
            'type' => 'composite',
            'providers' => [
                'os' => ['type' => 'system', 'property' => 'os'],
                'php' => ['type' => 'system', 'property' => 'phpVersion'],
            ],
        ]);
        $this->assertIsArray($result);
        $this->assertSame(\PHP_OS_FAMILY, $result['os']);
        $this->assertSame(\PHP_VERSION, $result['php']);
    }

    public function testResolveUnknownTypeReturnsNull(): void
    {
        $result = $this->resolver->resolve(['type' => 'nonexistent']);
        $this->assertNull($result);
    }

    public function testDefaultTypeIsCommand(): void
    {
        $result = $this->resolver->resolve(['command' => 'echo default']);
        $this->assertSame('default', $result);
    }

    public function testResolveGitBranch(): void
    {
        $result = $this->resolver->resolve(['type' => 'git', 'property' => 'branch']);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testResolveGitCommit(): void
    {
        $result = $this->resolver->resolve(['type' => 'git', 'property' => 'commit']);
        $this->assertIsString($result);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $result);
    }

    public function testResolveGitCommitShort(): void
    {
        $result = $this->resolver->resolve(['type' => 'git', 'property' => 'commitShort']);
        $this->assertIsString($result);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{7,}$/', $result);
    }

    public function testResolveGitDefaultPropertyIsBranch(): void
    {
        $resultWithDefault = $this->resolver->resolve(['type' => 'git']);
        $resultExplicit = $this->resolver->resolve(['type' => 'git', 'property' => 'branch']);
        $this->assertSame($resultExplicit, $resultWithDefault);
    }

    public function testResolveGitUnknownPropertyReturnsNull(): void
    {
        $result = $this->resolver->resolve(['type' => 'git', 'property' => 'nonexistent']);
        $this->assertNull($result);
    }

    public function testResolveScriptSimple(): void
    {
        $result = $this->resolver->resolve([
            'type' => 'script',
            'script' => "#!/bin/bash\necho hello",
        ]);
        $this->assertSame('hello', $result);
    }

    public function testResolveScriptNullScriptReturnsNull(): void
    {
        $result = $this->resolver->resolve(['type' => 'script']);
        $this->assertNull($result);
    }

    public function testResolveCommandFailedProcessReturnsNull(): void
    {
        $result = $this->resolver->resolve(['type' => 'command', 'command' => 'exit 1']);
        $this->assertNull($result);
    }

    public function testNoTypeKeyDefaultsToCommand(): void
    {
        $result = $this->resolver->resolve(['command' => 'echo no-type']);
        $this->assertSame('no-type', $result);
    }
}
