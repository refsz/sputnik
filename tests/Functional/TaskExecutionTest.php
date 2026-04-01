<?php

declare(strict_types=1);

namespace Sputnik\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Sputnik\Kernel;

final class TaskExecutionTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sputnik_test_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/tasks');
    }

    protected function tearDown(): void
    {
        $this->recursiveDelete($this->tempDir);
    }

    public function testSimpleTaskExecution(): void
    {
        $this->createConfig(<<<'NEON'
tasks:
    directories:
        - tasks
variables:
    constants:
        greeting: Hello
NEON);

        $this->createTask('GreetTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'greet', description: 'Greet someone')]
final class GreetTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $greeting = $ctx->get('greeting', 'Hi');
        return TaskResult::success($greeting . ' World');
    }
}
PHP);

        $kernel = new Kernel(workingDir: $this->tempDir);
        $runner = $kernel->getTaskRunner();

        $result = $runner->run('greet');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('Hello World', $result->message);
    }

    public function testTaskWithOptions(): void
    {
        $this->createConfig(<<<'NEON'
tasks:
    directories:
        - tasks
NEON);

        $this->createTask('EchoTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Attribute\Option;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'echo', description: 'Echo a message')]
final class EchoTask implements TaskInterface
{
    #[Option(name: 'message', description: 'Message to echo', default: 'default')]
    private string $message;

    public function __invoke(TaskContext $ctx): TaskResult
    {
        $message = $ctx->option('message');
        return TaskResult::success($message);
    }
}
PHP);

        $kernel = new Kernel(workingDir: $this->tempDir);
        $runner = $kernel->getTaskRunner();

        // Test with default
        $result = $runner->run('echo');
        $this->assertSame('default', $result->message);

        // Test with custom option
        $result = $runner->run('echo', [], ['message' => 'custom']);
        $this->assertSame('custom', $result->message);
    }

    public function testTaskWithArguments(): void
    {
        $this->createConfig(<<<'NEON'
tasks:
    directories:
        - tasks
NEON);

        $this->createTask('SayTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Attribute\Argument;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'say', description: 'Say something')]
final class SayTask implements TaskInterface
{
    #[Argument(name: 'text', description: 'Text to say')]
    private string $text;

    public function __invoke(TaskContext $ctx): TaskResult
    {
        $text = $ctx->argument('text', 'nothing');
        return TaskResult::success($text);
    }
}
PHP);

        $kernel = new Kernel(workingDir: $this->tempDir);
        $runner = $kernel->getTaskRunner();

        $result = $runner->run('say', ['text' => 'hello']);
        $this->assertSame('hello', $result->message);
    }

    public function testTaskWithContextVariables(): void
    {
        $this->createConfig(<<<'NEON'
tasks:
    directories:
        - tasks
contexts:
    local:
        description: Local
        variables:
            constants:
                env: development
    production:
        description: Production
        variables:
            constants:
                env: production
variables:
    constants:
        app: MyApp
defaults:
    context: local
NEON);

        $this->createTask('EnvTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'env', description: 'Show environment')]
final class EnvTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $app = $ctx->get('app');
        $env = $ctx->get('env');
        return TaskResult::success("{$app}:{$env}");
    }
}
PHP);

        // Test local context
        $kernel = new Kernel(workingDir: $this->tempDir, contextName: 'local');
        $result = $kernel->getTaskRunner()->run('env');
        $this->assertSame('MyApp:development', $result->message);

        // Test production context
        $kernel = new Kernel(workingDir: $this->tempDir, contextName: 'production');
        $result = $kernel->getTaskRunner()->run('env');
        $this->assertSame('MyApp:production', $result->message);
    }

    public function testTaskFailure(): void
    {
        $this->createConfig(<<<'NEON'
tasks:
    directories:
        - tasks
NEON);

        $this->createTask('FailTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'fail', description: 'Always fails')]
final class FailTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::failure('Something went wrong');
    }
}
PHP);

        $kernel = new Kernel(workingDir: $this->tempDir);
        $result = $kernel->getTaskRunner()->run('fail');

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('Something went wrong', $result->message);
    }

    public function testTaskException(): void
    {
        $this->createConfig(<<<'NEON'
tasks:
    directories:
        - tasks
NEON);

        $this->createTask('ExceptionTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Exception\RuntimeException as SputnikRuntimeException;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'exception', description: 'Throws exception')]
final class ExceptionTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        throw new SputnikRuntimeException('Task exploded');
    }
}
PHP);

        $kernel = new Kernel(workingDir: $this->tempDir);
        $result = $kernel->getTaskRunner()->run('exception');

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('Task exploded', $result->message);
    }

    public function testTaskSkipped(): void
    {
        $this->createConfig(<<<'NEON'
tasks:
    directories:
        - tasks
NEON);

        $this->createTask('SkipTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'skip', description: 'Skips execution')]
final class SkipTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::skipped('Nothing to do');
    }
}
PHP);

        $kernel = new Kernel(workingDir: $this->tempDir);
        $result = $kernel->getTaskRunner()->run('skip');

        $this->assertTrue($result->isSkipped());
        $this->assertSame('Nothing to do', $result->message);
    }

    public function testTaskRunsOtherTask(): void
    {
        $this->createConfig(<<<'NEON'
tasks:
    directories:
        - tasks
NEON);

        $this->createTask('FirstTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'first', description: 'First task')]
final class FirstTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success('first');
    }
}
PHP);

        $this->createTask('CompositeTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'composite', description: 'Runs other tasks')]
final class CompositeTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $result = $ctx->runTask('first');
        return TaskResult::success('composite:' . $result->message);
    }
}
PHP);

        $kernel = new Kernel(workingDir: $this->tempDir);
        $result = $kernel->getTaskRunner()->run('composite');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('composite:first', $result->message);
    }

    public function testTemplateRendersBeforeTask(): void
    {
        $this->createConfig(<<<'NEON'
tasks:
    directories:
        - tasks
variables:
    constants:
        app_name: TestApp
templates:
    config:
        src: templates/config.dist
        dist: config.txt
        overwrite: always
NEON);

        mkdir($this->tempDir . '/templates');
        file_put_contents(
            $this->tempDir . '/templates/config.dist',
            'APP_NAME={{ app_name }}',
        );

        $this->createTask('ReadConfigTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'read-config', description: 'Reads rendered config')]
final class ReadConfigTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $configPath = $ctx->getWorkingDir() . '/config.txt';
        if (!file_exists($configPath)) {
            return TaskResult::failure('Config not found');
        }
        $content = file_get_contents($configPath);
        return TaskResult::success($content);
    }
}
PHP);

        $kernel = new Kernel(workingDir: $this->tempDir);
        $result = $kernel->getTaskRunner()->run('read-config');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('APP_NAME=TestApp', $result->message);
    }

    public function testTaskAlias(): void
    {
        $this->createConfig(<<<'NEON'
tasks:
    directories:
        - tasks
NEON);

        $this->createTask('AliasTask', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Tasks;
use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'original', description: 'Has alias', aliases: ['alias', 'shortcut'])]
final class AliasTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        return TaskResult::success('works');
    }
}
PHP);

        $kernel = new Kernel(workingDir: $this->tempDir);
        $runner = $kernel->getTaskRunner();

        // All should work
        $this->assertTrue($runner->run('original')->isSuccessful());
        $this->assertTrue($runner->run('alias')->isSuccessful());
        $this->assertTrue($runner->run('shortcut')->isSuccessful());
    }

    private function createConfig(string $content): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', $content);
    }

    private function createTask(string $className, string $content): void
    {
        file_put_contents($this->tempDir . '/tasks/' . $className . '.php', $content);
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}
