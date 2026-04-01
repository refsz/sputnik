<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Sputnik\Attribute\Task;
use Sputnik\Console\Application;
use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskMetadata;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ApplicationTest extends TestCase
{
    public function testGetHelpReturnsEmptyString(): void
    {
        $app = new Application();
        $this->assertSame('', $app->getHelp());
    }

    public function testDoRunShowsSputnikHeader(): void
    {
        $app = new Application();
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'list']);

        $this->assertStringContainsString('Sputnik', $tester->getDisplay());
        $this->assertStringContainsString(Application::VERSION, $tester->getDisplay());
    }

    public function testDoRunShowsNoConfigWhenNotSet(): void
    {
        $app = new Application();
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'list']);

        $this->assertStringContainsString('no config', $tester->getDisplay());
    }

    public function testDoRunShowsConfigFileWhenSet(): void
    {
        $app = new Application();
        $app->setAutoExit(false);
        $app->setConfigFile('.sputnik.dist.neon');
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'list']);

        $this->assertStringContainsString('.sputnik.dist.neon', $tester->getDisplay());
    }

    public function testRenderTaskListShowsTaskNames(): void
    {
        $metadata = new TaskMetadata('FakeTask', new Task(name: 'db:migrate', description: 'Run migrations'));

        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('discoverAll')->willReturn(['db:migrate' => $metadata]);

        $app = new Application();
        $app->setAutoExit(false);
        $app->setTaskDiscovery($discovery);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'list']);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('db:migrate', $display);
        $this->assertStringContainsString('Run migrations', $display);
        $this->assertStringContainsString('Available tasks', $display);
    }

    public function testRenderTaskListWithAllHiddenTasksShowsNoSection(): void
    {
        $metadata = new TaskMetadata('HiddenTask', new Task(name: 'internal:task', hidden: true));

        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('discoverAll')->willReturn(['internal:task' => $metadata]);

        $app = new Application();
        $app->setAutoExit(false);
        $app->setTaskDiscovery($discovery);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'list']);

        $this->assertStringNotContainsString('Available tasks', $tester->getDisplay());
        $this->assertStringNotContainsString('internal:task', $tester->getDisplay());
    }

    public function testRenderTaskListGroupsTasksAndShowsGroupHeaders(): void
    {
        $taskA = new TaskMetadata('TaskA', new Task(name: 'db:migrate', description: 'Migrate DB', group: 'Database'));
        $taskB = new TaskMetadata('TaskB', new Task(name: 'cache:clear', description: 'Clear cache', group: 'Cache'));
        $taskC = new TaskMetadata('TaskC', new Task(name: 'ungrouped', description: 'No group'));

        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('discoverAll')->willReturn([
            'db:migrate' => $taskA,
            'cache:clear' => $taskB,
            'ungrouped' => $taskC,
        ]);

        $app = new Application();
        $app->setAutoExit(false);
        $app->setTaskDiscovery($discovery);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'list']);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Database', $display);
        $this->assertStringContainsString('Cache', $display);
        $this->assertStringContainsString('db:migrate', $display);
        $this->assertStringContainsString('cache:clear', $display);
        $this->assertStringContainsString('ungrouped', $display);
    }

    public function testRenderTaskListShowsEnvironmentTag(): void
    {
        $metadata = new TaskMetadata(
            'ContainerTask',
            new Task(name: 'docker:exec', description: 'Run in container', environment: 'container'),
        );

        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('discoverAll')->willReturn(['docker:exec' => $metadata]);

        $app = new Application();
        $app->setAutoExit(false);
        $app->setTaskDiscovery($discovery);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'list']);

        $this->assertStringContainsString('container', $tester->getDisplay());
    }

    public function testRenderTaskListShowsAliases(): void
    {
        $metadata = new TaskMetadata(
            'MigrateTask',
            new Task(name: 'db:migrate', description: 'Migrate', aliases: ['migrate', 'db:m']),
        );

        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('discoverAll')->willReturn(['db:migrate' => $metadata]);

        $app = new Application();
        $app->setAutoExit(false);
        $app->setTaskDiscovery($discovery);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'list']);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('migrate', $display);
        $this->assertStringContainsString('db:m', $display);
    }

    public function testRenderTaskListNotCalledWithoutDiscovery(): void
    {
        $app = new Application();
        $app->setAutoExit(false);
        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'list']);

        $this->assertStringNotContainsString('Available tasks', $tester->getDisplay());
    }
}
