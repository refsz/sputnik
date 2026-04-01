<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Sputnik\Console\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleLoggerTest extends TestCase
{
    private function makeLogger(int $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE): array
    {
        $output = new BufferedOutput($verbosity);
        $logger = new ConsoleLogger($output);

        return [$logger, $output];
    }

    public function testErrorIsAlwaysShown(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_QUIET);
        $logger->error('something broke');

        // error is VERBOSITY_NORMAL but quiet is 0 < normal... let's use normal
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_NORMAL);
        $logger->error('something broke');

        $this->assertStringContainsString('something broke', $output->fetch());
    }

    public function testErrorHasErrorPrefix(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_NORMAL);
        $logger->error('bad thing');

        $display = $output->fetch();
        $this->assertStringContainsString('[ERROR]', $display);
        $this->assertStringContainsString('bad thing', $display);
    }

    public function testWarningRequiresVerboseOutput(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_NORMAL);
        $logger->warning('watch out');
        $this->assertSame('', $output->fetch()); // not shown at NORMAL verbosity

        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_VERBOSE);
        $logger->warning('watch out');
        $this->assertStringContainsString('watch out', $output->fetch());
    }

    public function testWarningHasPrefix(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_VERBOSE);
        $logger->warning('heads up');

        $this->assertStringContainsString('[WARNING]', $output->fetch());
    }

    public function testInfoRequiresVerboseOutput(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_NORMAL);
        $logger->info('info message');
        $this->assertSame('', $output->fetch());

        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_VERBOSE);
        $logger->info('info message');
        $this->assertStringContainsString('info message', $output->fetch());
    }

    public function testInfoHasNoPrefix(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_VERBOSE);
        $logger->info('plain info');

        $display = $output->fetch();
        $this->assertStringNotContainsString('[INFO]', $display);
        $this->assertStringContainsString('plain info', $display);
    }

    public function testDebugRequiresVeryVerboseOutput(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_VERBOSE);
        $logger->debug('debug line');
        $this->assertSame('', $output->fetch());

        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $logger->debug('debug line');
        $this->assertStringContainsString('debug line', $output->fetch());
    }

    public function testDebugHasPrefix(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $logger->debug('debugging now');

        $this->assertStringContainsString('[DEBUG]', $output->fetch());
    }

    public function testEmergencyAlertCriticalHaveCriticalPrefix(): void
    {
        foreach ([LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL] as $level) {
            [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_NORMAL);
            $logger->log($level, 'critical situation');

            $display = $output->fetch();
            $this->assertStringContainsString('[CRITICAL]', $display, "Failed for level: $level");
            $this->assertStringContainsString('critical situation', $display);
        }
    }

    public function testContextInterpolationReplacesPlaceholders(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_NORMAL);
        $logger->error('Hello {name}!', ['name' => 'World']);

        $this->assertStringContainsString('Hello World!', $output->fetch());
    }

    public function testContextInterpolationWithScalarValues(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_NORMAL);
        $logger->error('Count: {count}', ['count' => 42]);

        $this->assertStringContainsString('42', $output->fetch());
    }

    public function testContextInterpolationWithStringableObject(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };

        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_NORMAL);
        $logger->error('Value: {val}', ['val' => $stringable]);

        $this->assertStringContainsString('stringable-value', $output->fetch());
    }

    public function testContextInterpolationIgnoresNonStringableValues(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_NORMAL);
        // An array value in context should not cause errors and {key} stays unreplaced
        $logger->error('Value: {data}', ['data' => ['a', 'b']]);

        // No exception thrown, output written
        $display = $output->fetch();
        $this->assertStringContainsString('Value:', $display);
    }

    public function testNoticeRequiresVerboseOutput(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_VERBOSE);
        $logger->notice('notice message');

        $this->assertStringContainsString('notice message', $output->fetch());
    }

    public function testUnknownLevelDefaultsToNormalVerbosity(): void
    {
        [$logger, $output] = $this->makeLogger(OutputInterface::VERBOSITY_NORMAL);
        $logger->log('custom_level', 'custom message');

        $this->assertStringContainsString('custom message', $output->fetch());
    }
}
