<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\Executor;

use PHPUnit\Framework\TestCase;
use Sputnik\Console\OutputChannel;
use Sputnik\Console\SputnikOutput;
use Sputnik\Executor\ExecutionException;
use Sputnik\Executor\ShellExecutor;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

final class ShellExecutorTest extends TestCase
{
    private ShellExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = new ShellExecutor();
    }

    public function testAColouredTerminalIsAnnouncedToTheCommand(): void
    {
        // A tool decides on colour by asking whether its output is a terminal,
        // and through Sputnik it never is: the output is captured so it can be
        // masked, inspected and indented. FORCE_COLOR says what the pipe cannot.
        $executor = new ShellExecutor($this->channel(decorated: true));

        $result = $executor->execute(['sh', '-c', 'printf "%s" "$FORCE_COLOR"']);

        $this->assertSame('1', $result->getOutput());
    }

    public function testARedirectedRunLeavesTheCommandMonochrome(): void
    {
        // Escape codes in a log file or behind a pipe would be worse than no
        // colour, so this follows Sputnik's own output.
        $executor = new ShellExecutor($this->channel(decorated: false));

        $result = $executor->execute(['sh', '-c', 'printf "%s" "$FORCE_COLOR"']);

        $this->assertSame('', $result->getOutput());
    }

    public function testAQuietCommandIsNeverColoured(): void
    {
        // Quiet means the output is a value, not a message. Escape codes in a
        // value corrupt whatever reads it - a byte comparison, a JSON decode.
        $executor = new ShellExecutor($this->channel(decorated: true));

        $result = $executor->execute(['sh', '-c', 'printf "%s" "$FORCE_COLOR"'], ['quiet' => true]);

        $this->assertSame('', $result->getOutput());
    }

    public function testTheTaskKeepsTheLastWordOnTheEnvironment(): void
    {
        $executor = new ShellExecutor($this->channel(decorated: true));

        $result = $executor->execute(
            ['sh', '-c', 'printf "%s" "$FORCE_COLOR"'],
            ['env' => ['FORCE_COLOR' => '']],
        );

        $this->assertSame('', $result->getOutput());
    }

    public function testExecuteSuccessfulCommand(): void
    {
        $result = $this->executor->execute('echo "hello world"');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(0, $result->exitCode);
        $this->assertStringContainsString('hello world', $result->output);
        $this->assertSame('echo "hello world"', $result->command);
        $this->assertGreaterThan(0.0, $result->duration);
    }

    public function testExecuteFailingCommand(): void
    {
        $result = $this->executor->execute('exit 42');

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(42, $result->exitCode);
    }

    public function testTheQuietOptionSuppressesStreaming(): void
    {
        $output = new BufferedOutput();
        $channel = new OutputChannel();
        $channel->set($output);
        $executor = new ShellExecutor($channel);

        $result = $executor->execute('echo "quiet test"', ['quiet' => true]);

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('quiet test', $result->output);
        $this->assertEmpty($output->fetch());
    }

    public function testExecuteStreamsToOutput(): void
    {
        $output = new BufferedOutput();
        $channel = new OutputChannel();
        $channel->set($output);
        $executor = new ShellExecutor($channel);

        $result = $executor->execute('echo "streamed"');

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('streamed', $output->fetch());
    }

    public function testExecuteWithCwd(): void
    {
        $result = $this->executor->execute('pwd', ['cwd' => '/tmp']);

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('/tmp', trim($result->output));
    }

    public function testExecuteWithEnvVariables(): void
    {
        $result = $this->executor->execute(
            'echo $SPUTNIK_TEST_VAR',
            ['env' => ['SPUTNIK_TEST_VAR' => 'test_value']],
        );

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('test_value', $result->output);
    }

    /**
     * @group slow
     */
    public function testExecuteWithTimeout(): void
    {
        $this->expectException(ProcessTimedOutException::class);

        $this->executor->execute('sleep 10', ['timeout' => 0.5]);
    }

    public function testExecuteCapturesErrorOutput(): void
    {
        $result = $this->executor->execute('echo "error" >&2');

        $this->assertStringContainsString('error', $result->errorOutput);
    }

    public function testAssertSuccessThrowsOnFailure(): void
    {
        $result = $this->executor->execute('exit 1');

        $this->expectException(ExecutionException::class);
        $result->assertSuccess();
    }

    public function testExecuteEchoesCommandViaSputnikOutput(): void
    {
        $buffer = new BufferedOutput();
        $sputnikOutput = new SputnikOutput($buffer, '0.1.0', '.sputnik.dist.neon', 'dev');

        $channel = new OutputChannel();
        $channel->set($sputnikOutput->getOutput(), $sputnikOutput);
        $executor = new ShellExecutor($channel);
        $executor->execute('echo "test output"');

        $display = $buffer->fetch();
        $this->assertStringContainsString('> echo "test output"', $display);
    }

    public function testExecuteShowsCommandDoneForMultiStepTasks(): void
    {
        $buffer = new BufferedOutput();
        $sputnikOutput = new SputnikOutput($buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $sputnikOutput->setTotalSteps(2);

        $channel = new OutputChannel();
        $channel->set($sputnikOutput->getOutput(), $sputnikOutput);
        $executor = new ShellExecutor($channel);
        $executor->execute('echo "done"');

        $display = $buffer->fetch();
        $this->assertMatchesRegularExpression('/[✓✗]/', $display);
    }

    public function testExecuteSkipsCommandDoneForSingleStepTasks(): void
    {
        $buffer = new BufferedOutput();
        $sputnikOutput = new SputnikOutput($buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $sputnikOutput->setTotalSteps(1);

        $channel = new OutputChannel();
        $channel->set($sputnikOutput->getOutput(), $sputnikOutput);
        $executor = new ShellExecutor($channel);
        $executor->execute('echo "done"');

        $display = $buffer->fetch();
        $this->assertStringContainsString('> echo "done"', $display);
        $this->assertDoesNotMatchRegularExpression('/[✓✗]/', $display);
    }

    public function testAnExplicitAnsiRequestStillReachesTheCommand(): void
    {
        // NO_COLOR in the environment and --ansi on the command line contradict
        // each other. Symfony resolves that for its own output in favour of the
        // flag, which is why the channel is decorated here - so Sputnik follows
        // it rather than colouring its own output while leaving the command's
        // monochrome. Where the command honours NO_COLOR itself, as Symfony
        // Console and the Node tools do, it still comes out without colour.
        $executor = new ShellExecutor($this->channel(decorated: true));

        $result = $executor->execute(['sh', '-c', 'printf "%s" "$FORCE_COLOR"']);

        $this->assertSame('1', $result->getOutput());
    }

    private function channel(bool $decorated): OutputChannel
    {
        $output = new BufferedOutput();
        $output->setDecorated($decorated);

        $channel = new OutputChannel();
        $channel->set($output);

        return $channel;
    }
}
