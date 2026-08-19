<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Environment;

use PHPUnit\Framework\TestCase;
use Sputnik\Environment\EnvironmentDetector;
use Sputnik\Exception\RuntimeException;

final class EnvironmentDetectorWrapTest extends TestCase
{
    public function testArgvIsPrependedWithTheExecutor(): void
    {
        $detector = new EnvironmentDetector(detection: 'false', executor: ['ddev', 'exec']);

        $this->assertSame(
            ['ddev', 'exec', 'drush', 'cr'],
            $detector->wrapCommand(['drush', 'cr'], 'container'),
        );
    }

    public function testArgumentBoundariesSurviveWrapping(): void
    {
        $detector = new EnvironmentDetector(detection: 'false', executor: ['ddev', 'exec']);

        $this->assertSame(
            ['ddev', 'exec', 'rm', '-rf', 'x; composer install'],
            $detector->wrapCommand(['rm', '-rf', 'x; composer install'], 'container'),
        );
    }

    public function testShellStringBecomesASingleArgumentOfTheDefaultShell(): void
    {
        $detector = new EnvironmentDetector(detection: 'false', executor: ['ddev', 'exec']);

        $this->assertSame(
            ['ddev', 'exec', 'sh', '-c', 'drush dump | gzip'],
            $detector->wrapCommand('drush dump | gzip', 'container'),
        );
    }

    public function testConfiguredShellReplacesTheDefault(): void
    {
        $detector = new EnvironmentDetector(
            detection: 'false',
            executor: ['ddev', 'exec'],
            shell: ['bash', '-lc'],
        );

        $this->assertSame(
            ['ddev', 'exec', 'bash', '-lc', 'composer install'],
            $detector->wrapCommand('composer install', 'container'),
        );
    }

    public function testHostTaskIsNotWrapped(): void
    {
        $detector = new EnvironmentDetector(detection: 'false', executor: ['ddev', 'exec']);

        $this->assertSame(['git', 'status'], $detector->wrapCommand(['git', 'status'], 'host'));
        $this->assertSame('git status | head', $detector->wrapCommand('git status | head', 'host'));
    }

    public function testTaskWithoutEnvironmentIsNotWrapped(): void
    {
        $detector = new EnvironmentDetector(detection: 'false', executor: ['ddev', 'exec']);

        $this->assertSame(['git', 'status'], $detector->wrapCommand(['git', 'status'], null));
    }

    public function testContainerTaskWithoutExecutorFails(): void
    {
        $detector = new EnvironmentDetector(detection: 'false');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('executor');

        $detector->wrapCommand(['drush', 'cr'], 'container');
    }

    public function testHostTaskInsideAContainerFails(): void
    {
        $detector = new EnvironmentDetector(detection: 'true', executor: ['ddev', 'exec']);

        $this->expectException(RuntimeException::class);

        $detector->wrapCommand(['git', 'status'], 'host');
    }

    public function testInsideAContainerNothingIsWrapped(): void
    {
        $detector = new EnvironmentDetector(detection: 'true', executor: ['ddev', 'exec']);

        $this->assertSame(['drush', 'cr'], $detector->wrapCommand(['drush', 'cr'], 'container'));
        $this->assertSame('drush cr | head', $detector->wrapCommand('drush cr | head', 'container'));
    }
}
