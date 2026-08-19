<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\Console;

use PHPUnit\Framework\TestCase;
use Sputnik\Console\OutputChannel;
use Sputnik\Console\SputnikOutput;
use Sputnik\Executor\ShellExecutor;
use Sputnik\Secret\RedactingOutput;
use Sputnik\Secret\SecretRedactor;
use Sputnik\Secret\SecretRegistry;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A listener receives the same executor and the same channel a task does, so
 * what it runs is visible and masked. Before the channel existed, the container
 * built an executor with no output at all and everything a listener ran was
 * silent - the reason listeners in the field resorted to echo, which bypasses
 * redaction.
 */
final class ListenerOutputTest extends TestCase
{
    public function testAListenerCommandStreamsItsOutput(): void
    {
        $buffer = new BufferedOutput();
        $channel = new OutputChannel();
        $channel->set($buffer);
        $executor = new ShellExecutor($channel);

        $executor->execute(['echo', 'from-listener']);

        $this->assertStringContainsString('from-listener', $buffer->fetch());
    }

    public function testAListenerCommandIsEchoedWhenTheChannelCarriesTaskOutput(): void
    {
        $buffer = new BufferedOutput();
        $channel = new OutputChannel();
        $channel->set($buffer, new SputnikOutput($buffer, '0.0.0', 'config', 'test'));
        $executor = new ShellExecutor($channel);

        $executor->execute(['echo', 'hello']);

        $printed = $buffer->fetch();
        $this->assertStringContainsString('> echo hello', $printed);
        $this->assertStringContainsString('hello', $printed);
    }

    public function testAListenerCommandIsMaskedLikeATaskCommand(): void
    {
        $registry = new SecretRegistry();
        $registry->declareSecrets(['apiToken']);
        $registry->remember('apiToken', 'ghp_abcdefghij');

        $buffer = new BufferedOutput();
        $channel = new OutputChannel();
        $channel->set(new RedactingOutput($buffer, new SecretRedactor($registry)));
        $executor = new ShellExecutor($channel);

        $executor->execute(['echo', 'ghp_abcdefghij']);

        $printed = $buffer->fetch();
        $this->assertStringNotContainsString('ghp_abcdefghij', $printed);
        $this->assertStringContainsString('***', $printed);
    }

    public function testWithoutAFilledChannelNothingIsPrintedAndNothingFails(): void
    {
        $executor = new ShellExecutor(new OutputChannel());

        $result = $executor->execute(['echo', 'silent']);

        // The command still runs and its output is on the result; it just has
        // nowhere to be printed, which is what happens before a command starts.
        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('silent', $result->getOutput());
    }
}
