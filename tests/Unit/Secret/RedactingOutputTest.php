<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Secret;

use PHPUnit\Framework\TestCase;
use Sputnik\Secret\RedactingConsoleOutput;
use Sputnik\Secret\RedactingOutput;
use Sputnik\Secret\SecretRedactor;
use Sputnik\Secret\SecretRegistry;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

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

        $errorBuffer = new BufferedOutput();
        $console = new ConsoleOutput();
        $console->setErrorOutput($errorBuffer);

        $decorated = new RedactingConsoleOutput($console, new SecretRedactor($registry));
        $decorated->getErrorOutput()->writeln('token ghp_abcdefghij');

        $this->assertSame("token ***\n", $errorBuffer->fetch());
    }

    public function testMixedIterableRedactsStringable(): void
    {
        $this->output->writeln([
            new class {
                public function __toString(): string
                {
                    return 'token ghp_abcdefghij';
                }
            },
            42,
            3.14,
        ]);

        $output = $this->inner->fetch();
        $this->assertStringContainsString('token ***', $output);
        $this->assertStringContainsString('42', $output);
        $this->assertStringContainsString('3.14', $output);
    }

    public function testNonStringableItemsPassThroughUntouched(): void
    {
        $plainObject = new class {};
        $secret = 'token ghp_abcdefghij';

        $registry = new SecretRegistry();
        $registry->declareSecrets(['apiToken']);
        $registry->remember('apiToken', 'ghp_abcdefghij');

        $recordingOutput = new RecordingOutput();
        $output = new RedactingOutput($recordingOutput, new SecretRedactor($registry));

        $output->write([$secret, $plainObject]);

        $this->assertCount(1, $recordingOutput->capturedMessages);
        $messages = $recordingOutput->capturedMessages[0];

        $this->assertIsArray($messages);
        $this->assertCount(2, $messages);
        $this->assertSame('token ***', $messages[0]);
        $this->assertSame($plainObject, $messages[1]);
    }

    public function testSectionWritelnIsRedacted(): void
    {
        $registry = new SecretRegistry();
        $registry->declareSecrets(['apiToken']);
        $registry->remember('apiToken', 'ghp_abcdefghij');

        $stream = fopen('php://memory', 'w+');
        $console = new MemoryConsoleOutput($stream);
        $decorated = new RedactingConsoleOutput($console, new SecretRedactor($registry));

        $section = $decorated->section();
        $section->writeln('token ghp_abcdefghij');

        rewind($stream);
        $this->assertSame("token ***\n", stream_get_contents($stream));
    }

    public function testTableRenderedThroughSymfonyStyleIsRedacted(): void
    {
        $registry = new SecretRegistry();
        $registry->declareSecrets(['apiToken']);
        $registry->remember('apiToken', 'ghp_abcdefghij');

        $stream = fopen('php://memory', 'w+');
        $console = new MemoryConsoleOutput($stream);
        $decorated = new RedactingConsoleOutput($console, new SecretRedactor($registry));

        $io = new SymfonyStyle(new ArrayInput([], new InputDefinition()), $decorated);
        $io->table(['Header'], [['ghp_abcdefghij']]);

        rewind($stream);
        $contents = stream_get_contents($stream);

        $this->assertStringContainsString('***', $contents);
        $this->assertStringNotContainsString('ghp_abcdefghij', $contents);
    }
}

/**
 * @internal test helper: a ConsoleOutputInterface backed by a real (memory)
 * stream, so RedactingConsoleOutput::section() can build a redacting section
 * from it exactly as it would from a real ConsoleOutput
 */
final class MemoryConsoleOutput extends StreamOutput implements ConsoleOutputInterface
{
    private OutputInterface $stderr;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        parent::__construct($stream, self::VERBOSITY_NORMAL, false);
        $this->stderr = new BufferedOutput();
    }

    public function getErrorOutput(): OutputInterface
    {
        return $this->stderr;
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        $this->stderr = $error;
    }

    public function section(): ConsoleSectionOutput
    {
        throw new \LogicException('Not used: RedactingConsoleOutput builds its own section from getStream().');
    }
}

/**
 * @internal Test helper: records messages passed to write() before formatting
 */
final class RecordingOutput extends Output
{
    /**
     * @var array<int, mixed>
     */
    public array $capturedMessages = [];

    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        $this->capturedMessages[] = $messages;
    }

    protected function doWrite(string $message, bool $newline): void
    {
        // Do not render anything
    }
}
