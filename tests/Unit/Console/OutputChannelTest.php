<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Sputnik\Console\OutputChannel;
use Sputnik\Console\SputnikOutput;
use Symfony\Component\Console\Output\BufferedOutput;

final class OutputChannelTest extends TestCase
{
    public function testAnEmptyChannelWritesNowhereInsteadOfFailing(): void
    {
        $channel = new OutputChannel();

        $channel->writeln('into the void');

        $this->assertNull($channel->output());
    }

    public function testWritelnReachesTheConfiguredOutput(): void
    {
        $buffer = new BufferedOutput();
        $channel = new OutputChannel();
        $channel->set($buffer);

        $channel->writeln('hello');

        $this->assertSame("hello\n", $buffer->fetch());
    }

    public function testWriteOmitsTheNewline(): void
    {
        $buffer = new BufferedOutput();
        $channel = new OutputChannel();
        $channel->set($buffer);

        $channel->write('hello');

        $this->assertSame('hello', $buffer->fetch());
    }

    public function testSputnikOutputTakesPrecedenceOverThePlainOutput(): void
    {
        $plain = new BufferedOutput();
        $wrapped = new BufferedOutput();
        $channel = new OutputChannel();
        $channel->set($plain, new SputnikOutput($wrapped, '0.0.0', 'config', 'test'));

        $channel->writeln('hello');

        // The task-shaped output owns the destination once it exists.
        $this->assertSame("hello\n", $wrapped->fetch());
        $this->assertSame('', $plain->fetch());
    }

    public function testSputnikOutputIsExposedForStepAwareCallers(): void
    {
        $buffer = new BufferedOutput();
        $sputnikOutput = new SputnikOutput($buffer, '0.0.0', 'config', 'test');
        $channel = new OutputChannel();

        $this->assertNull($channel->sputnikOutput());

        $channel->set($buffer, $sputnikOutput);

        $this->assertSame($sputnikOutput, $channel->sputnikOutput());
    }

    public function testStyledHelpersWrapTheMessage(): void
    {
        $buffer = new BufferedOutput();
        $channel = new OutputChannel();
        $channel->set($buffer);

        $channel->success('done');
        $channel->comment('note');

        // BufferedOutput strips the tags, which proves they were tags and not text.
        $this->assertSame("done\nnote\n", $buffer->fetch());
    }

    public function testSettingAgainReplacesTheDestination(): void
    {
        $first = new BufferedOutput();
        $second = new BufferedOutput();
        $channel = new OutputChannel();

        $channel->set($first);
        $channel->set($second);
        $channel->writeln('hello');

        $this->assertSame('', $first->fetch());
        $this->assertSame("hello\n", $second->fetch());
    }
}
