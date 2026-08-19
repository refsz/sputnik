<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\DependencyInjection;

use Sputnik\Config\Configuration;
use Sputnik\DependencyInjection\ContainerFactory;
use Sputnik\Secret\SecretRedactor;
use Sputnik\Secret\SecretRegistry;
use Sputnik\Tests\Support\TestCase;
use Sputnik\Variable\VariableResolver;

final class SecretServicesWiringTest extends TestCase
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

    public function testContainerProvidesSecretRedactor(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $redactor = $container->getByType(SecretRedactor::class);

        $this->assertInstanceOf(SecretRedactor::class, $redactor);
    }

    public function testContainerProvidesSecretRegistry(): void
    {
        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $registry = $container->getByType(SecretRegistry::class);

        $this->assertInstanceOf(SecretRegistry::class, $registry);
    }

    /**
     * The redactor and the variable resolver must be handed the same
     * SecretRegistry instance, or a value resolved through the resolver
     * would never be known to the redactor. There is no accessor exposing
     * the resolver's internal registry, so this is proven behaviourally:
     * resolve the secret through the resolver taken from the container,
     * then show the redactor taken from the same container masks it.
     */
    public function testRegistrySharedBetweenResolverAndRedactorIsTheSameInstance(): void
    {
        $config = new Configuration([
            'variables' => [
                'secrets' => [
                    'apiToken' => 'ghp_abcdefghij',
                ],
            ],
        ]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');

        $container = $factory->create();
        $resolver = $container->getByType(VariableResolver::class);
        $redactor = $container->getByType(SecretRedactor::class);
        $registry = $container->getByType(SecretRegistry::class);

        // Before the secret is accessed, the registry the redactor reads has
        // no value for it yet, so redaction is a no-op.
        $this->assertSame(
            'token ghp_abcdefghij',
            $redactor->redact('token ghp_abcdefghij'),
        );

        $resolver->resolve('apiToken');

        // The value is now known to the registry retrieved independently
        // from the container...
        $this->assertSame(['ghp_abcdefghij'], $registry->values());

        // ...and to the redactor, which proves both services were wired to
        // the same SecretRegistry instance rather than separate copies.
        $this->assertSame(
            'token ***',
            $redactor->redact('token ghp_abcdefghij'),
        );
    }
}
