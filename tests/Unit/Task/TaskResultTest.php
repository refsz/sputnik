<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Sputnik\Task\TaskResult;
use Sputnik\Task\TaskStatus;

final class TaskResultTest extends TestCase
{
    public function testSuccessCreatesSuccessfulResult(): void
    {
        $result = TaskResult::success('Done');

        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->isFailure());
        $this->assertFalse($result->isSkipped());
        $this->assertSame(TaskStatus::Success, $result->status);
        $this->assertSame('Done', $result->message);
        $this->assertSame(0, $result->exitCode);
    }

    public function testSuccessWithoutMessage(): void
    {
        $result = TaskResult::success();

        $this->assertTrue($result->isSuccessful());
        $this->assertNull($result->message);
    }

    public function testSuccessWithData(): void
    {
        $result = TaskResult::success('Done', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $result->data);
    }

    public function testFailureCreatesFailedResult(): void
    {
        $result = TaskResult::failure('Something went wrong');

        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isSkipped());
        $this->assertSame(TaskStatus::Failure, $result->status);
        $this->assertSame('Something went wrong', $result->message);
        $this->assertSame(1, $result->exitCode);
    }

    public function testFailureWithCustomExitCode(): void
    {
        $result = TaskResult::failure('Error', [], 42);

        $this->assertSame(42, $result->exitCode);
    }

    public function testSkippedCreatesSkippedResult(): void
    {
        $result = TaskResult::skipped('Already up to date');

        $this->assertFalse($result->isSuccessful());
        $this->assertFalse($result->isFailure());
        $this->assertTrue($result->isSkipped());
        $this->assertSame(TaskStatus::Skipped, $result->status);
        $this->assertSame('Already up to date', $result->message);
        $this->assertSame(0, $result->exitCode);
    }

    public function testWithDurationReturnsNewInstance(): void
    {
        $result = TaskResult::success('Done');
        $withDuration = $result->withDuration(1.5);

        $this->assertNull($result->duration);
        $this->assertSame(1.5, $withDuration->duration);
        $this->assertNotSame($result, $withDuration);
    }

    public function testWithDataMergesData(): void
    {
        $result = TaskResult::success('Done', ['a' => 1]);
        $withData = $result->withData(['b' => 2]);

        $this->assertSame(['a' => 1], $result->data);
        $this->assertSame(['a' => 1, 'b' => 2], $withData->data);
    }
}
