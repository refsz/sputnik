<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Environment;

use PHPUnit\Framework\TestCase;
use Sputnik\Environment\EnvironmentDetector;
use Sputnik\Exception\RuntimeException as SputnikRuntimeException;

final class EnvironmentDetectorTest extends TestCase
{
    public function testDefaultDetectionReturnsBoolean(): void
    {
        $detector = new EnvironmentDetector();
        $this->assertIsBool($detector->isContainer());
    }

    public function testCustomDetectionTrue(): void
    {
        $detector = new EnvironmentDetector(detection: 'true');
        $this->assertTrue($detector->isContainer());
    }

    public function testCustomDetectionFalse(): void
    {
        $detector = new EnvironmentDetector(detection: 'false');
        $this->assertFalse($detector->isContainer());
    }

    public function testWrapCommandWhenOnHost(): void
    {
        $detector = new EnvironmentDetector(
            detection: 'false',
            executor: 'docker compose exec -T app {command}',
        );

        $result = $detector->wrapCommand('composer install', 'container');
        $this->assertSame('docker compose exec -T app composer install', $result);
    }

    public function testWrapCommandWhenInContainer(): void
    {
        $detector = new EnvironmentDetector(
            detection: 'true',
            executor: 'docker compose exec -T app {command}',
        );

        $result = $detector->wrapCommand('composer install', 'container');
        $this->assertSame('composer install', $result);
    }

    public function testWrapCommandWithHostEnvironment(): void
    {
        $detector = new EnvironmentDetector(
            detection: 'false',
            executor: 'docker compose exec -T app {command}',
        );

        $result = $detector->wrapCommand('docker compose up', 'host');
        $this->assertSame('docker compose up', $result);
    }

    public function testWrapCommandWithNullEnvironment(): void
    {
        $detector = new EnvironmentDetector(
            detection: 'false',
            executor: 'docker compose exec -T app {command}',
        );

        $result = $detector->wrapCommand('echo hello', null);
        $this->assertSame('echo hello', $result);
    }

    public function testContainerTaskWithoutExecutorThrows(): void
    {
        $detector = new EnvironmentDetector(detection: 'false');

        $this->expectException(SputnikRuntimeException::class);
        $this->expectExceptionMessageMatches('/executor/');
        $detector->wrapCommand('composer install', 'container');
    }

    public function testHostTaskInsideContainerThrows(): void
    {
        $detector = new EnvironmentDetector(
            detection: 'true',
            executor: 'docker compose exec -T app {command}',
        );

        $this->expectException(SputnikRuntimeException::class);
        $this->expectExceptionMessageMatches('/Host task/');
        $detector->wrapCommand('docker compose up', 'host');
    }

    public function testGetExecutor(): void
    {
        $detector = new EnvironmentDetector(executor: 'ddev exec {command}');
        $this->assertSame('ddev exec {command}', $detector->getExecutor());
    }

    public function testGetExecutorReturnsNullWhenNotConfigured(): void
    {
        $detector = new EnvironmentDetector();
        $this->assertNull($detector->getExecutor());
    }
}
