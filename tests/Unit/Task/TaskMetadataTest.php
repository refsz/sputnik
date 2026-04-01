<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskMetadata;

final class TaskMetadataTest extends TestCase
{
    private function makeMetadata(string $name, array $aliases = []): TaskMetadata
    {
        return new TaskMetadata(
            className: 'App\Task\FakeTask',
            attribute: new Task(name: $name, description: 'A test task', aliases: $aliases),
        );
    }

    public function testMatchesReturnsTrueForExactName(): void
    {
        $metadata = $this->makeMetadata('db:migrate');

        $this->assertTrue($metadata->matches('db:migrate'));
    }

    public function testMatchesReturnsTrueForAlias(): void
    {
        $metadata = $this->makeMetadata('db:migrate', ['migrate', 'db:m']);

        $this->assertTrue($metadata->matches('migrate'));
        $this->assertTrue($metadata->matches('db:m'));
    }

    public function testMatchesReturnsFalseForUnknownName(): void
    {
        $metadata = $this->makeMetadata('db:migrate', ['migrate']);

        $this->assertFalse($metadata->matches('db:rollback'));
        $this->assertFalse($metadata->matches(''));
        $this->assertFalse($metadata->matches('DB:MIGRATE'));
    }

    public function testMatchesWithNoAliases(): void
    {
        $metadata = $this->makeMetadata('deploy:app');

        $this->assertTrue($metadata->matches('deploy:app'));
        $this->assertFalse($metadata->matches('deploy'));
    }

    public function testMatchesIsCaseSensitive(): void
    {
        $metadata = $this->makeMetadata('deploy:app', ['Deploy:App']);

        $this->assertFalse($metadata->matches('Deploy:app'));
        $this->assertTrue($metadata->matches('Deploy:App'));
    }
}
