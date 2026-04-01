<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\Executor;

use PHPUnit\Framework\TestCase;
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

    public function testExecuteQuietSuppressesStreaming(): void
    {
        $output = new BufferedOutput();
        $executor = new ShellExecutor($output);

        $result = $executor->executeQuiet('echo "quiet test"');

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('quiet test', $result->output);
        $this->assertEmpty($output->fetch());
    }

    public function testExecuteStreamsToOutput(): void
    {
        $output = new BufferedOutput();
        $executor = new ShellExecutor($output);

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

        $executor = new ShellExecutor(sputnikOutput: $sputnikOutput);
        $executor->execute('echo "test output"');

        $display = $buffer->fetch();
        $this->assertStringContainsString('> echo "test output"', $display);
    }

    public function testExecuteShowsCommandDoneForMultiStepTasks(): void
    {
        $buffer = new BufferedOutput();
        $sputnikOutput = new SputnikOutput($buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $sputnikOutput->setTotalSteps(2);

        $executor = new ShellExecutor(sputnikOutput: $sputnikOutput);
        $executor->execute('echo "done"');

        $display = $buffer->fetch();
        $this->assertMatchesRegularExpression('/[✓✗]/', $display);
    }

    public function testExecuteSkipsCommandDoneForSingleStepTasks(): void
    {
        $buffer = new BufferedOutput();
        $sputnikOutput = new SputnikOutput($buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $sputnikOutput->setTotalSteps(1);

        $executor = new ShellExecutor(sputnikOutput: $sputnikOutput);
        $executor->execute('echo "done"');

        $display = $buffer->fetch();
        $this->assertStringContainsString('> echo "done"', $display);
        $this->assertDoesNotMatchRegularExpression('/[✓✗]/', $display);
    }
}
