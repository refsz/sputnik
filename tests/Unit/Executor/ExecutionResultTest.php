<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Executor;

use PHPUnit\Framework\TestCase;
use Sputnik\Executor\ExecutionException;
use Sputnik\Executor\ExecutionResult;

final class ExecutionResultTest extends TestCase
{
    public function testIsSuccessfulReturnsTrueForZeroExitCode(): void
    {
        $result = new ExecutionResult(
            exitCode: 0,
            output: 'success',
            errorOutput: '',
            duration: 0.1,
            command: 'echo success',
        );

        $this->assertTrue($result->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseForNonZeroExitCode(): void
    {
        $result = new ExecutionResult(
            exitCode: 1,
            output: '',
            errorOutput: 'error',
            duration: 0.1,
            command: 'exit 1',
        );

        $this->assertFalse($result->isSuccessful());
    }

    public function testGetOutputReturnsOutput(): void
    {
        $result = new ExecutionResult(
            exitCode: 0,
            output: 'hello world',
            errorOutput: '',
            duration: 0.1,
            command: 'echo hello world',
        );

        $this->assertSame('hello world', $result->getOutput());
    }

    public function testGetErrorOutputReturnsErrorOutput(): void
    {
        $result = new ExecutionResult(
            exitCode: 1,
            output: '',
            errorOutput: 'something went wrong',
            duration: 0.1,
            command: 'bad_command',
        );

        $this->assertSame('something went wrong', $result->getErrorOutput());
    }

    public function testGetCombinedOutputReturnsBothOutputs(): void
    {
        $result = new ExecutionResult(
            exitCode: 0,
            output: 'stdout',
            errorOutput: 'stderr',
            duration: 0.1,
            command: 'command',
        );

        $this->assertSame('stdoutstderr', $result->getCombinedOutput());
    }

    public function testAssertSuccessReturnsSelfOnSuccess(): void
    {
        $result = new ExecutionResult(
            exitCode: 0,
            output: '',
            errorOutput: '',
            duration: 0.1,
            command: 'true',
        );

        $this->assertSame($result, $result->assertSuccess());
    }

    public function testAssertSuccessThrowsOnFailure(): void
    {
        $result = new ExecutionResult(
            exitCode: 42,
            output: '',
            errorOutput: 'error message',
            duration: 0.1,
            command: 'failing_command',
        );

        $this->expectException(ExecutionException::class);
        $this->expectExceptionMessage('Command failed with exit code 42');

        $result->assertSuccess();
    }

    public function testExceptionContainsErrorOutput(): void
    {
        $result = new ExecutionResult(
            exitCode: 1,
            output: '',
            errorOutput: 'detailed error',
            duration: 0.1,
            command: 'command',
        );

        try {
            $result->assertSuccess();
            $this->fail('Expected ExecutionException');
        } catch (ExecutionException $e) {
            $this->assertSame('detailed error', $e->errorOutput);
            $this->assertSame(1, $e->exitCode);
        }
    }

    public function testPublicProperties(): void
    {
        $result = new ExecutionResult(
            exitCode: 0,
            output: 'out',
            errorOutput: 'err',
            duration: 1.5,
            command: 'cmd',
        );

        $this->assertSame(0, $result->exitCode);
        $this->assertSame('out', $result->output);
        $this->assertSame('err', $result->errorOutput);
        $this->assertSame(1.5, $result->duration);
        $this->assertSame('cmd', $result->command);
    }
}
