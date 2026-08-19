<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\Executor;

use PHPUnit\Framework\TestCase;
use Sputnik\Executor\ShellExecutor;

final class ShellExecutorArgvTest extends TestCase
{
    private ShellExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = new ShellExecutor();
    }

    public function testArgvRunsWithoutAShell(): void
    {
        // A shell would split at the semicolon and expand the substitution;
        // through argv both stay one literal argument.
        $result = $this->executor->execute(['printf', '%s', 'a; echo pwned $(id -u)']);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('a; echo pwned $(id -u)', $result->getOutput());
    }

    public function testArgvKeepsAnArgumentWithSpacesTogether(): void
    {
        $result = $this->executor->execute(['printf', '[%s]', 'two words']);

        $this->assertSame('[two words]', $result->getOutput());
    }

    public function testArgvDoesNotExpandGlobs(): void
    {
        $result = $this->executor->execute(['printf', '%s', '*']);

        $this->assertSame('*', $result->getOutput());
    }

    public function testStringStillRunsThroughAShell(): void
    {
        $result = $this->executor->execute('printf "%s" one | tr a-z A-Z');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('ONE', $result->getOutput());
    }

    public function testEmptyArgvIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->executor->execute([]);
    }

    public function testExecutionResultShowsTheJoinedArgvAsTheCommand(): void
    {
        $result = $this->executor->execute(['printf', '%s', 'x']);

        $this->assertSame('printf %s x', $result->command);
    }

    public function testFailingArgvReportsItsExitCode(): void
    {
        $result = $this->executor->execute(['sh', '-c', 'exit 3']);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(3, $result->exitCode);
    }
}
