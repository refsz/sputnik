<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Secret;

use PHPUnit\Framework\TestCase;
use Sputnik\Secret\RedactingConsoleOutput;
use Sputnik\Secret\RedactingOutput;
use Sputnik\Secret\SecretRedactor;
use Sputnik\Secret\SecretRegistry;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class RedactingOutputTest extends TestCase
{
    private BufferedOutput $inner;

    private RedactingOutput $output;

    protected function setUp(): void
    {
        $registry = new SecretRegistry();
        $registry->declareSecrets(['apiToken']);
        $registry->remember('apiToken', 'ghp_abcdefghij');

        $this->inner = new BufferedOutput();
        $this->output = new RedactingOutput($this->inner, new SecretRedactor($registry));
    }

    public function testWritelnIsRedacted(): void
    {
        $this->output->writeln('token ghp_abcdefghij');

        $this->assertSame("token ***\n", $this->inner->fetch());
    }

    public function testWriteIsRedacted(): void
    {
        $this->output->write('token ghp_abcdefghij');

        $this->assertSame('token ***', $this->inner->fetch());
    }

    public function testArrayOfMessagesIsRedacted(): void
    {
        $this->output->writeln(['a ghp_abcdefghij', 'b']);

        $this->assertSame("a ***\nb\n", $this->inner->fetch());
    }

    public function testRawOutputIsRedacted(): void
    {
        $this->output->write('ghp_abcdefghij', false, OutputInterface::OUTPUT_RAW);

        $this->assertSame('***', $this->inner->fetch());
    }

    public function testStyleTagsSurvive(): void
    {
        $this->output->writeln('<info>ok</info>');

        $this->assertSame("ok\n", $this->inner->fetch());
    }

    public function testVerbosityIsDelegated(): void
    {
        $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $this->assertSame(OutputInterface::VERBOSITY_DEBUG, $this->inner->getVerbosity());
        $this->assertSame(OutputInterface::VERBOSITY_DEBUG, $this->output->getVerbosity());
        $this->assertTrue($this->output->isDebug());
    }

    public function testFormatterIsDelegated(): void
    {
        $this->assertSame($this->inner->getFormatter(), $this->output->getFormatter());
    }

    public function testConsoleVariantRedactsErrorOutput(): void
    {
        $registry = new SecretRegistry();
        $registry->declareSecrets(['apiToken']);
        $registry->remember('apiToken', 'ghp_abcdefghij');

        $console = new ConsoleOutput();
        $decorated = new RedactingConsoleOutput($console, new SecretRedactor($registry));

        $this->assertInstanceOf(RedactingOutput::class, $decorated->getErrorOutput());
    }

    public function testMixedIterablePreservesNonStrings(): void
    {
        $this->output->writeln([
            'secret: ghp_abcdefghij',
            42,
            new class {
                public function __toString(): string
                {
                    return 'stringable object';
                }
            },
        ]);

        $output = $this->inner->fetch();
        $this->assertStringContainsString('secret: ***', $output);
        $this->assertStringContainsString('42', $output);
        $this->assertStringContainsString('stringable object', $output);
    }
}
