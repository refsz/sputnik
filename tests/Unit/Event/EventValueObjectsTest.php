<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Sputnik\Attribute\Task;
use Sputnik\Event\AfterTaskEvent;
use Sputnik\Event\TaskFailedEvent;
use Sputnik\Event\TemplateRenderedEvent;
use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskResult;
use Sputnik\Template\TemplateConfig;

final class EventValueObjectsTest extends TestCase
{
    private TaskMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new TaskMetadata('FakeTask', new Task(name: 'test:task'));
    }

    // --- AfterTaskEvent ---

    public function testAfterTaskEventGettersReturnConstructedValues(): void
    {
        $result = TaskResult::success('done');
        $event = new AfterTaskEvent($this->metadata, $result, 1.23);

        $this->assertSame($this->metadata, $event->task);
        $this->assertSame($result, $event->result);
        $this->assertSame(1.23, $event->duration);
    }

    public function testAfterTaskEventIsSuccessfulDelegatesToResult(): void
    {
        $event = new AfterTaskEvent($this->metadata, TaskResult::success(), 0.1);
        $this->assertTrue($event->isSuccessful());

        $eventFailed = new AfterTaskEvent($this->metadata, TaskResult::failure('oops'), 0.1);
        $this->assertFalse($eventFailed->isSuccessful());
    }

    // --- TaskFailedEvent ---

    public function testTaskFailedEventGettersReturnConstructedValues(): void
    {
        $exception = new \RuntimeException('something broke');
        $event = new TaskFailedEvent($this->metadata, $exception);

        $this->assertSame($this->metadata, $event->task);
        $this->assertSame($exception, $event->exception);
    }

    public function testTaskFailedEventGetErrorMessageReturnsExceptionMessage(): void
    {
        $exception = new \RuntimeException('my error message');
        $event = new TaskFailedEvent($this->metadata, $exception);

        $this->assertSame('my error message', $event->getErrorMessage());
    }

    // --- TemplateRenderedEvent ---

    public function testTemplateRenderedEventGettersReturnConstructedValues(): void
    {
        $config = new TemplateConfig('env', 'src/env.dist', '.env');
        $event = new TemplateRenderedEvent($config, '/output/.env', true, null);

        $this->assertSame($config, $event->template);
        $this->assertSame('/output/.env', $event->outputPath);
        $this->assertTrue($event->written);
        $this->assertNull($event->skipReason);
    }

    public function testTemplateRenderedEventWasWrittenAndWasSkipped(): void
    {
        $config = new TemplateConfig('env', 'src/env.dist', '.env');

        $written = new TemplateRenderedEvent($config, '/out', true);
        $this->assertTrue($written->wasWritten());
        $this->assertFalse($written->wasSkipped());

        $skipped = new TemplateRenderedEvent($config, '/out', false, 'overwrite=never');
        $this->assertFalse($skipped->wasWritten());
        $this->assertTrue($skipped->wasSkipped());
        $this->assertSame('overwrite=never', $skipped->skipReason);
    }
}
