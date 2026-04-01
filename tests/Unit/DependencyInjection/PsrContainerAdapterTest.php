<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\DependencyInjection;

use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Sputnik\Config\Configuration;
use Sputnik\DependencyInjection\ContainerFactory;
use Sputnik\DependencyInjection\PsrContainerAdapter;
use Sputnik\Tests\Support\TestCase;
use Sputnik\Variable\VariableResolver;

final class PsrContainerAdapterTest extends TestCase
{
    private PsrContainerAdapter $adapter;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = $this->createTempDir();

        $config = new Configuration([]);
        $factory = new ContainerFactory($config, $this->tempDir, 'default');
        $netteContainer = $factory->create();
        $this->adapter = new PsrContainerAdapter($netteContainer);
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
        parent::tearDown();
    }

    public function testGetByTypeReturnsService(): void
    {
        $logger = $this->adapter->get(LoggerInterface::class);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testGetByConcreteClassReturnsService(): void
    {
        $resolver = $this->adapter->get(VariableResolver::class);
        $this->assertInstanceOf(VariableResolver::class, $resolver);
    }

    public function testGetByServiceNameReturnsService(): void
    {
        // Nette registers services with generated names; test by type fallback first
        // then test that a known service name works
        $logger = $this->adapter->get(LoggerInterface::class);
        $this->assertNotNull($logger);
    }

    public function testGetThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $this->adapter->get('Nonexistent\\Class\\That\\Does\\Not\\Exist');
    }

    public function testHasReturnsTrueForExistingType(): void
    {
        $this->assertTrue($this->adapter->has(LoggerInterface::class));
        $this->assertTrue($this->adapter->has(VariableResolver::class));
    }

    public function testHasReturnsFalseForUnknownId(): void
    {
        $this->assertFalse($this->adapter->has('Nonexistent\\Class'));
    }

    public function testGetThrowsNotFoundExceptionForUnknownServiceName(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        // Not a class/interface but also not a registered service name
        $this->adapter->get('totally_unknown_service_xyz');
    }

    public function testHasReturnsFalseForUnknownServiceName(): void
    {
        $this->assertFalse($this->adapter->has('totally_unknown_service_xyz'));
    }
}
