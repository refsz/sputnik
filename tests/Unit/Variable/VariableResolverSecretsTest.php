<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Variable;

use PHPUnit\Framework\TestCase;
use Sputnik\Config\Configuration;
use Sputnik\Exception\InvalidConfigException;
use Sputnik\Secret\SecretRegistry;
use Sputnik\Variable\VariableResolver;

final class VariableResolverSecretsTest extends TestCase
{
    public function testSecretIsResolvedOnAccess(): void
    {
        $resolver = $this->resolver([
            'apiToken' => ['type' => 'command', 'command' => 'echo ghp_abcdefghij'],
        ]);

        $this->assertSame('ghp_abcdefghij', $resolver->resolve('apiToken'));
    }

    public function testLiteralSecretIsResolved(): void
    {
        $resolver = $this->resolver(['legacyKey' => 'literal-value']);

        $this->assertSame('literal-value', $resolver->resolve('legacyKey'));
    }

    public function testSecretIsNotResolvedUntilAccessed(): void
    {
        $marker = sys_get_temp_dir() . '/sputnik_secret_marker_' . uniqid();
        $resolver = $this->resolver([
            'apiToken' => ['type' => 'command', 'command' => 'touch ' . escapeshellarg($marker) . ' && echo value'],
        ]);

        $resolver->resolve('other', 'fallback');
        $this->assertFileDoesNotExist($marker);

        $resolver->resolve('apiToken');
        $this->assertFileExists($marker);

        unlink($marker);
    }

    public function testHasDoesNotResolve(): void
    {
        $marker = sys_get_temp_dir() . '/sputnik_secret_marker_' . uniqid();
        $resolver = $this->resolver([
            'apiToken' => ['type' => 'command', 'command' => 'touch ' . escapeshellarg($marker) . ' && echo value'],
        ]);

        $this->assertTrue($resolver->has('apiToken'));
        $this->assertFileDoesNotExist($marker);
    }

    public function testResolvedValueIsRememberedByTheRegistry(): void
    {
        $registry = new SecretRegistry();
        $resolver = $this->resolver(
            ['apiToken' => ['type' => 'command', 'command' => 'echo ghp_abcdefghij']],
            $registry,
        );

        $resolver->resolve('apiToken');

        $this->assertSame(['ghp_abcdefghij'], $registry->values());
    }

    public function testRuntimeOverrideOfASecretStaysClassifiedAndRemembered(): void
    {
        $registry = new SecretRegistry();
        $resolver = $this->resolver(
            ['apiToken' => ['type' => 'command', 'command' => 'echo from-command']],
            $registry,
        )->withOverrides(['apiToken' => 'from-override']);

        $this->assertSame('from-override', $resolver->resolve('apiToken'));
        $this->assertSame(['from-override'], $registry->values());
    }

    public function testUnresolvableSecretIsNullAndReportsDiagnostic(): void
    {
        $registry = new SecretRegistry();
        $resolver = $this->resolver(
            ['apiToken' => ['type' => 'command', 'command' => 'exit 1']],
            $registry,
        );

        $this->assertNull($resolver->resolve('apiToken'));
        $this->assertSame(["secret 'apiToken' could not be resolved"], $registry->takeDiagnostics());
    }

    public function testNameInTwoSourcesIsAConfigurationError(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => ['apiToken' => 'plain'],
                'secrets' => ['apiToken' => 'secret'],
            ],
        ]);

        $resolver = new VariableResolver($config, null, sys_get_temp_dir(), new SecretRegistry());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('apiToken');

        $resolver->resolve('apiToken');
    }

    public function testNameInAContextConstantIsAConfigurationError(): void
    {
        $config = new Configuration([
            'contexts' => [
                'dev' => ['variables' => ['constants' => ['apiToken' => 'plain']]],
            ],
            'variables' => [
                'secrets' => ['apiToken' => 'secret'],
            ],
        ]);

        $resolver = new VariableResolver($config, 'dev', sys_get_temp_dir(), new SecretRegistry());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('apiToken');

        $resolver->resolve('apiToken');
    }

    public function testUnsupportedSecretTypeIsAConfigurationError(): void
    {
        $resolver = $this->resolver(['branch' => ['type' => 'git', 'property' => 'branch']]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('git');

        $resolver->resolve('branch');
    }

    /**
     * @param array<string, mixed> $secrets
     */
    private function resolver(array $secrets, ?SecretRegistry $registry = null): VariableResolver
    {
        $config = new Configuration(['variables' => ['secrets' => $secrets]]);

        return new VariableResolver($config, null, sys_get_temp_dir(), $registry ?? new SecretRegistry());
    }
}
