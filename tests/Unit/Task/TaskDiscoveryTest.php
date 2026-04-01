<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Task;

use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskDiscoveryException;
use Sputnik\Task\TaskMetadata;
use Sputnik\Tests\Support\TestCase;

final class TaskDiscoveryTest extends TestCase
{
    private TaskDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discovery = new TaskDiscovery(
            directories: [$this->fixture('Tasks')],
        );
    }

    public function testDiscoversTasksFromDirectory(): void
    {
        $tasks = $this->discovery->discoverAll();

        $this->assertNotEmpty($tasks);
        $this->assertContainsOnlyInstancesOf(TaskMetadata::class, $tasks);
    }

    public function testFindsTaskByName(): void
    {
        $task = $this->discovery->getTask('test:simple');

        $this->assertNotNull($task);
        $this->assertSame('test:simple', $task->getName());
    }

    public function testReturnsNullForUnknownTask(): void
    {
        $task = $this->discovery->getTask('unknown:task');

        $this->assertNull($task);
    }

    public function testFindsTaskByAlias(): void
    {
        $task = $this->discovery->getTask('simple');

        $this->assertNotNull($task);
        $this->assertSame('test:simple', $task->getName());
    }

    public function testHasTaskReturnsTrueForExistingTask(): void
    {
        $this->assertTrue($this->discovery->hasTask('test:simple'));
        $this->assertTrue($this->discovery->hasTask('simple'));
    }

    public function testHasTaskReturnsFalseForNonExistingTask(): void
    {
        $this->assertFalse($this->discovery->hasTask('non:existing'));
    }

    public function testExtractsOptionsFromTask(): void
    {
        $task = $this->discovery->getTask('test:with-options');

        $this->assertNotNull($task);
        $this->assertCount(2, $task->options);
        $this->assertSame('mode', $task->options[0]->name);
        $this->assertSame('verbose', $task->options[1]->name);
    }

    public function testExtractsArgumentsFromTask(): void
    {
        $task = $this->discovery->getTask('test:with-options');

        $this->assertNotNull($task);
        $this->assertCount(1, $task->arguments);
        $this->assertSame('target', $task->arguments[0]->name);
    }

    public function testExtractsTaskGroup(): void
    {
        $task = $this->discovery->getTask('test:with-options');

        $this->assertNotNull($task);
        $this->assertSame('test', $task->getGroup());
    }

    public function testGetTaskNamesReturnsAllNames(): void
    {
        $names = $this->discovery->getTaskNames();

        $this->assertContains('test:simple', $names);
        $this->assertContains('test:with-options', $names);
    }

    public function testHandlesEmptyDirectory(): void
    {
        $tempDir = $this->createTempDir();

        try {
            $discovery = new TaskDiscovery([$tempDir]);
            $tasks = $discovery->discoverAll();

            $this->assertEmpty($tasks);
        } finally {
            $this->removeTempDir($tempDir);
        }
    }

    public function testHandlesNonExistentDirectory(): void
    {
        $discovery = new TaskDiscovery(['/non/existent/path']);
        $tasks = $discovery->discoverAll();

        $this->assertEmpty($tasks);
    }

    public function testDuplicateTaskNameThrows(): void
    {
        $discovery = new TaskDiscovery([$this->fixture('DuplicateTasks')]);

        $this->expectException(TaskDiscoveryException::class);
        $this->expectExceptionMessageMatches('/duplicate:name/');
        $discovery->discoverAll();
    }

    public function testDuplicateAliasThrows(): void
    {
        $discovery = new TaskDiscovery([$this->fixture('DuplicateAliasTasks')]);

        $this->expectException(TaskDiscoveryException::class);
        $this->expectExceptionMessageMatches('/shared/');
        $discovery->discoverAll();
    }

    public function testAliasCollidingWithTaskNameThrows(): void
    {
        $discovery = new TaskDiscovery([$this->fixture('AliasCollidesWithName')]);

        $this->expectException(TaskDiscoveryException::class);
        $this->expectExceptionMessageMatches('/real:name/');
        $discovery->discoverAll();
    }
}
