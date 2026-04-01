<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Attribute;

use PHPUnit\Framework\TestCase;
use Sputnik\Attribute\Task;

final class TaskAttributeTest extends TestCase
{
    public function testConstructWithMinimalArguments(): void
    {
        $task = new Task(name: 'my:task');

        $this->assertSame('my:task', $task->name);
        $this->assertSame('', $task->description);
        $this->assertSame([], $task->aliases);
        $this->assertNull($task->group);
        $this->assertFalse($task->hidden);
    }

    public function testConstructWithAllArguments(): void
    {
        $task = new Task(
            name: 'db:migrate',
            description: 'Run migrations',
            aliases: ['migrate', 'db:m'],
            group: 'database',
            hidden: true,
        );

        $this->assertSame('db:migrate', $task->name);
        $this->assertSame('Run migrations', $task->description);
        $this->assertSame(['migrate', 'db:m'], $task->aliases);
        $this->assertSame('database', $task->group);
        $this->assertTrue($task->hidden);
    }

    public function testIsAttribute(): void
    {
        $reflection = new \ReflectionClass(Task::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);
    }

    public function testTargetsClass(): void
    {
        $reflection = new \ReflectionClass(Task::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $attribute = $attributes[0]->newInstance();

        $this->assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    public function testEnvironmentDefaultsToNull(): void
    {
        $task = new Task(name: 'test');
        $this->assertNull($task->environment);
    }

    public function testEnvironmentContainer(): void
    {
        $task = new Task(name: 'test', environment: 'container');
        $this->assertSame('container', $task->environment);
    }

    public function testEnvironmentHost(): void
    {
        $task = new Task(name: 'test', environment: 'host');
        $this->assertSame('host', $task->environment);
    }
}
