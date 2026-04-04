<?php

declare(strict_types=1);

namespace Sputnik\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class SputnikBinaryTest extends TestCase
{
    private static string $binary;

    private string $tempDir;

    public static function setUpBeforeClass(): void
    {
        self::$binary = \dirname(__DIR__, 2) . '/bin/sputnik';
    }

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sputnik_e2e_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    // ── Basics ──────────────────────────────────────────────────

    public function testVersionOutput(): void
    {
        $result = $this->sputnik(['--version']);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Sputnik', $result->getOutput());
    }

    public function testListShowsHeader(): void
    {
        $result = $this->sputnik(['list']);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Sputnik', $result->getOutput());
        $this->assertStringContainsString('PHP', $result->getOutput());
    }

    public function testListShowsAvailableCommands(): void
    {
        $result = $this->sputnik(['list']);

        $output = $result->getOutput();
        $this->assertStringContainsString('init', $output);
        $this->assertStringContainsString('run', $output);
        $this->assertStringContainsString('context:switch', $output);
        $this->assertStringContainsString('context:list', $output);
    }

    // ── Init ────────────────────────────────────────────────────

    public function testInitCreatesProjectStructure(): void
    {
        $result = $this->sputnik(['init'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertFileExists($this->tempDir . '/.sputnik.dist.neon');
        $this->assertFileExists($this->tempDir . '/sputnik/ExampleTask.php');
    }

    public function testInitHelp(): void
    {
        $result = $this->sputnik(['init', '--help']);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('.sputnik.dist.neon', $result->getOutput());
    }

    // ── Task execution ──────────────────────────────────────────

    public function testInitAndRunExampleTask(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['example'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Hello', $result->getOutput());
    }

    public function testInitAndRunExampleTaskViaRunCommand(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['run', 'example'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Hello', $result->getOutput());
    }

    public function testRunNonExistentTaskFails(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['run', 'nonexistent'], $this->tempDir);

        $this->assertNotSame(0, $result->getExitCode());
    }

    public function testTaskOptionWithValue(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['example', '--name', 'Sputnik'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Sputnik', $result->getOutput());
    }

    public function testTaskOptionWithValueViaRunCommand(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['run', 'example', '--', '--name', 'Sputnik'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Sputnik', $result->getOutput());
    }

    // ── Runtime variables ───────────────────────────────────────

    public function testRuntimeVariableOverride(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['example', '-D', 'app_name=TestApp', '-v'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('TestApp', $result->getOutput());
    }

    public function testRuntimeVariableOverrideViaRunCommand(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['run', 'example', '-D', 'app_name=TestApp', '-v'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('TestApp', $result->getOutput());
    }

    public function testSubtaskInheritsRuntimeOverrides(): void
    {
        $this->scaffoldProject([
            'inner' => <<<'PHP'
                #[Task(name: 'inner', description: 'Inner task')]
                final class InnerTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        $ctx->success('value=' . $ctx->get('myvar', 'default'));
                        return TaskResult::success();
                    }
                }
                PHP,
            'outer' => <<<'PHP'
                #[Task(name: 'outer', description: 'Outer task')]
                final class OuterTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        $ctx->runTask('inner');
                        return TaskResult::success();
                    }
                }
                PHP,
        ]);

        $result = $this->sputnik(['outer', '-D', 'myvar=override', '-v'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('value=override', $result->getOutput());
    }

    // ── Working dir ─────────────────────────────────────────────

    public function testWorkingDirOption(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['--working-dir=' . $this->tempDir, 'example']);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('Hello', $result->getOutput());
    }

    // ── Config display ──────────────────────────────────────────

    public function testNoConfigShowsNoConfigInHeader(): void
    {
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir, 0755, true);

        $result = $this->sputnik(['list'], $emptyDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('no config', $result->getOutput());
    }

    public function testWithConfigShowsConfigInHeader(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $result = $this->sputnik(['list'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('.sputnik.dist.neon', $result->getOutput());
    }

    public function testBothConfigsShownInHeader(): void
    {
        $this->sputnik(['init'], $this->tempDir);
        file_put_contents($this->tempDir . '/.sputnik.neon', '# local overrides');

        $result = $this->sputnik(['list'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('.sputnik.dist.neon + .sputnik.neon', $result->getOutput());
    }

    public function testOnlyLocalConfigShownInHeader(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.neon', "tasks:\n    directories:\n        - sputnik");

        $result = $this->sputnik(['list'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('.sputnik.neon', $result->getOutput());
        $this->assertStringNotContainsString('.sputnik.dist.neon', $result->getOutput());
    }

    // ── Context ─────────────────────────────────────────────────

    public function testContextSwitchAndList(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $listResult = $this->sputnik(['context:list'], $this->tempDir);
        $this->assertSame(0, $listResult->getExitCode());
        $this->assertStringContainsString('local', $listResult->getOutput());

        $switchResult = $this->sputnik(['context:switch', 'staging'], $this->tempDir);
        $this->assertSame(0, $switchResult->getExitCode());
    }

    public function testContextPersistsAcrossProcesses(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        $this->sputnik(['context:switch', 'staging'], $this->tempDir);

        // New process should see the persisted context
        $result = $this->sputnik(['context:list'], $this->tempDir);
        $this->assertSame(0, $result->getExitCode());

        // The header of any command should show the active context
        $taskResult = $this->sputnik(['example', '-v'], $this->tempDir);
        $this->assertSame(0, $taskResult->getExitCode());
        $this->assertStringContainsString('staging', $taskResult->getOutput());
    }

    // ── Error handling ──────────────────────────────────────────

    public function testInvalidConfigShowsCleanError(): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', "bad: [unclosed\n");

        $result = $this->sputnik(['list'], $this->tempDir);

        $this->assertNotSame(0, $result->getExitCode());
        $output = $result->getOutput() . $result->getErrorOutput();
        $this->assertStringContainsString('Error', $output);
    }

    public function testTaskExceptionShowsCleanError(): void
    {
        $this->scaffoldProject([
            'failing' => <<<'PHP'
                #[Task(name: 'failing', description: 'A task that throws')]
                final class FailingTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        throw new \RuntimeException('Something went wrong');
                    }
                }
                PHP,
        ]);

        $result = $this->sputnik(['failing'], $this->tempDir);

        $this->assertNotSame(0, $result->getExitCode());
        $this->assertStringContainsString('Something went wrong', $result->getOutput());
    }

    public function testTaskExceptionShowsTraceWithVerbose(): void
    {
        $this->scaffoldProject([
            'failing' => <<<'PHP'
                #[Task(name: 'failing', description: 'A task that throws')]
                final class FailingTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        throw new \RuntimeException('Verbose error');
                    }
                }
                PHP,
        ]);

        $result = $this->sputnik(['failing', '-v'], $this->tempDir);

        $this->assertNotSame(0, $result->getExitCode());
        $this->assertStringContainsString('Verbose error', $result->getOutput());
    }

    public function testInvalidTaskOptionShowsError(): void
    {
        $this->scaffoldProject([
            'typed' => <<<'PHP'
                use Sputnik\Attribute\Option;

                #[Task(name: 'typed', description: 'Task with choices')]
                final class TypedTask implements TaskInterface
                {
                    #[Option(name: 'env', description: 'Environment', choices: ['dev', 'prod'])]
                    private string $env;

                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        return TaskResult::success();
                    }
                }
                PHP,
        ]);

        $result = $this->sputnik(['typed', '--env', 'invalid'], $this->tempDir);

        $this->assertNotSame(0, $result->getExitCode());
    }

    public function testReservedTaskNameShowsError(): void
    {
        $this->scaffoldProject([
            'init' => <<<'PHP'
                #[Task(name: 'init', description: 'Collides with built-in')]
                final class InitTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        return TaskResult::success();
                    }
                }
                PHP,
        ]);

        $result = $this->sputnik(['list'], $this->tempDir);

        $this->assertNotSame(0, $result->getExitCode());
        $output = $result->getOutput() . $result->getErrorOutput();
        $this->assertStringContainsString('reserved', $output);
    }

    // ── Aliases ─────────────────────────────────────────────────

    public function testTaskAliasWorks(): void
    {
        $this->scaffoldProject([
            'aliased' => <<<'PHP'
                #[Task(name: 'deploy:production', description: 'Deploy', aliases: ['deploy'])]
                final class DeployTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        $ctx->success('deployed');
                        return TaskResult::success();
                    }
                }
                PHP,
        ]);

        $result = $this->sputnik(['deploy'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('deployed', $result->getOutput());
    }

    public function testTaskAliasWorksViaRunCommand(): void
    {
        $this->scaffoldProject([
            'aliased' => <<<'PHP'
                #[Task(name: 'deploy:production', description: 'Deploy', aliases: ['deploy'])]
                final class DeployTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        $ctx->success('deployed');
                        return TaskResult::success();
                    }
                }
                PHP,
        ]);

        $result = $this->sputnik(['run', 'deploy'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('deployed', $result->getOutput());
    }

    // ── Templates ───────────────────────────────────────────────

    public function testTemplateRendering(): void
    {
        $this->scaffoldConfig(<<<'NEON'
            tasks:
                directories:
                    - sputnik

            variables:
                constants:
                    appName: MySputnikApp

            templates:
                env:
                    src: templates/.env.dist
                    dist: .env
                    overwrite: always
            NEON);

        $this->writeTask('greeter', <<<'PHP'
            #[Task(name: 'greeter', description: 'Greet')]
            final class GreeterTask implements TaskInterface
            {
                public function __invoke(TaskContext $ctx): TaskResult
                {
                    return TaskResult::success();
                }
            }
            PHP);

        mkdir($this->tempDir . '/templates', 0755, true);
        file_put_contents($this->tempDir . '/templates/.env.dist', 'APP_NAME={{ appName }}');

        $result = $this->sputnik(['greeter'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertFileExists($this->tempDir . '/.env');
        $this->assertSame('APP_NAME=MySputnikApp', file_get_contents($this->tempDir . '/.env'));
    }

    // ── Environment executor ───────────────────────────────────

    public function testEnvironmentExecutorWrapsContainerTasks(): void
    {
        $this->scaffoldConfig(<<<'NEON'
            tasks:
                directories:
                    - sputnik

            environment:
                executor: "bash -c {command}"
            NEON);

        $this->writeTask('container', <<<'PHP'
            #[Task(name: 'container:hello', description: 'Runs in container', environment: 'container')]
            final class ContainerTask implements TaskInterface
            {
                public function __invoke(TaskContext $ctx): TaskResult
                {
                    $ctx->shellRaw('echo "from-container"');
                    return TaskResult::success();
                }
            }
            PHP);

        $result = $this->sputnik(['container:hello'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('from-container', $result->getOutput());
    }

    public function testHostTaskBypassesExecutor(): void
    {
        $this->scaffoldConfig(<<<'NEON'
            tasks:
                directories:
                    - sputnik

            environment:
                executor: "false {command}"
            NEON);

        $this->writeTask('hostonly', <<<'PHP'
            #[Task(name: 'host:hello', description: 'Runs on host', environment: 'host')]
            final class HostonlyTask implements TaskInterface
            {
                public function __invoke(TaskContext $ctx): TaskResult
                {
                    $ctx->shellRaw('echo "from-host"');
                    return TaskResult::success();
                }
            }
            PHP);

        $result = $this->sputnik(['host:hello'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('from-host', $result->getOutput());
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * @param array<string> $args
     */
    private function sputnik(array $args, ?string $cwd = null): Process
    {
        $process = new Process(
            ['php', self::$binary, ...$args],
            $cwd,
            ['COLUMNS' => '120'],
        );
        $process->run();

        return $process;
    }

    /**
     * Scaffold a project with config and one or more tasks.
     *
     * @param array<string, string> $tasks task name => task body (without use statements and PHP open tag)
     */
    private function scaffoldProject(array $tasks): void
    {
        $this->scaffoldConfig(<<<'NEON'
            tasks:
                directories:
                    - sputnik
            NEON);

        foreach ($tasks as $name => $body) {
            $this->writeTask($name, $body);
        }
    }

    private function scaffoldConfig(string $neon): void
    {
        file_put_contents($this->tempDir . '/.sputnik.dist.neon', $neon);
    }

    private function writeTask(string $name, string $body): void
    {
        $taskDir = $this->tempDir . '/sputnik';
        if (!is_dir($taskDir)) {
            mkdir($taskDir, 0755, true);
        }

        $className = ucfirst($name) . 'Task';

        // Check if body already has use statements for Option/Argument
        $extraUse = '';
        if (str_contains($body, 'Sputnik\Attribute\Option')) {
            // Already in body
        }

        $content = <<<PHP
            <?php
            declare(strict_types=1);
            use Sputnik\Attribute\Task;
            use Sputnik\Attribute\Option;
            use Sputnik\Attribute\Argument;
            use Sputnik\Task\TaskContext;
            use Sputnik\Task\TaskInterface;
            use Sputnik\Task\TaskResult;

            {$body}
            PHP;

        file_put_contents($taskDir . '/' . $className . '.php', $content);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
