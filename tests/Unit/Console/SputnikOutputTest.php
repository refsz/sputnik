<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Sputnik\Console\SputnikOutput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SputnikOutputTest extends TestCase
{
    private BufferedOutput $buffer;

    protected function setUp(): void
    {
        $this->buffer = new BufferedOutput();
    }

    public function testHeaderShowsVersionConfigAndContext(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->header();

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('Sputnik', $display);
        $this->assertStringContainsString('0.1.0', $display);
        $this->assertStringContainsString('.sputnik.dist.neon', $display);
        $this->assertStringContainsString('dev', $display);
    }

    public function testHeaderShowsLocalConfigWhenProvided(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon + .sputnik.neon', 'dev');
        $out->header();

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('.sputnik.neon', $display);
    }

    public function testTaskStartShowsNameAndDescription(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->taskStart('docker:ssh', 'Connect into the app container');

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('docker:ssh', $display);
        $this->assertStringContainsString('Connect into the app container', $display);
    }

    public function testCommandEchoWithStepCounter(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->setTotalSteps(3);
        $out->command('composer install');

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('(1/3)', $display);
        $this->assertStringContainsString('> composer install', $display);
    }

    public function testCommandEchoWithoutCounterForSingleStep(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->setTotalSteps(1);
        $out->command('docker exec -it abc bash');

        $display = $this->buffer->fetch();
        $this->assertStringNotContainsString('(1/1)', $display);
        $this->assertStringContainsString('> docker exec', $display);
    }

    public function testCommandEchoIncrementsStepCounter(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->setTotalSteps(2);
        $out->command('first');
        $this->buffer->fetch();
        $out->command('second');

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('(2/2)', $display);
    }

    public function testSuccessWithDuration(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->success(1.234);

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('Done', $display);
        $this->assertStringContainsString('1.23', $display);
    }

    public function testFailureShowsMessage(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->failure('Connection refused');

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('Failed', $display);
        $this->assertStringContainsString('Connection refused', $display);
    }

    public function testSkippedShowsMessage(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->skipped('Already running');

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('Skipped', $display);
        $this->assertStringContainsString('Already running', $display);
    }

    public function testCommandDoneShowsCheckmarkOnSuccess(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->setTotalSteps(2);
        $out->commandDone(2.5, 0);

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('2.50', $display);
    }

    public function testCommandDoneSkippedForSingleStep(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->setTotalSteps(1);
        $out->commandDone(2.5, 0);

        $display = $this->buffer->fetch();
        $this->assertSame('', $display);
    }

    public function testCommandDoneShowsCrossOnFailure(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->setTotalSteps(2);
        $out->commandDone(1.0, 1);

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('1.00', $display);
    }

    public function testResetStepCounter(): void
    {
        $out = new SputnikOutput($this->buffer, '0.1.0', '.sputnik.dist.neon', 'dev');
        $out->setTotalSteps(2);
        $out->command('first');
        $this->buffer->fetch();

        $out->resetSteps();
        $out->setTotalSteps(3);
        $out->command('nested-first');

        $display = $this->buffer->fetch();
        $this->assertStringContainsString('(1/3)', $display);
    }
}
