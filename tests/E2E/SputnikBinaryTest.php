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

    public function testContextOverrideDoesNotPersist(): void
    {
        $this->sputnik(['init'], $this->tempDir);

        // Run with --context override
        $result = $this->sputnik(['--context', 'production', 'example', '-v'], $this->tempDir);
        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('production', $result->getOutput());

        // Next run without --context should be back to default (local)
        $result = $this->sputnik(['example', '-v'], $this->tempDir);
        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('local', $result->getOutput());
    }

    public function testContextOverrideRerendersTemplatesBackAfterTheRun(): void
    {
        $this->scaffoldConfig(<<<'NEON'
            tasks:
                directories:
                    - sputnik

            contexts:
                production:
                    description: Production

            templates:
                env:
                    src: templates/.env.dist
                    dist: .env
            NEON);
        $this->writeTask('noop', <<<'PHP'
            #[Task(name: 'noop', description: 'Does nothing')]
            final class NoopTask implements TaskInterface
            {
                public function __invoke(TaskContext $ctx): TaskResult
                {
                    return TaskResult::success();
                }
            }
            PHP);
        mkdir($this->tempDir . '/templates', 0755, true);
        file_put_contents($this->tempDir . '/templates/.env.dist', 'CTX={{ context }}');

        $result = $this->sputnik(['--context', 'production', 'noop'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        // The run rendered with the override, but the override is one-shot:
        // afterwards the template must reflect the persisted context again.
        $this->assertSame('CTX=local', file_get_contents($this->tempDir . '/.env'));
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

    public function testAProjectTaskMayTakeTheInitName(): void
    {
        $this->scaffoldProject([
            'init' => <<<'PHP'
                #[Task(name: 'init', description: 'The project has its own init')]
                final class InitTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        $ctx->writeln('project init ran');

                        return TaskResult::success();
                    }
                }
                PHP,
        ]);

        $result = $this->sputnik(['init'], $this->tempDir);
        $output = $result->getOutput() . $result->getErrorOutput();

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('project init ran', $output);
        $this->assertStringContainsString('shadows', $output);
        $this->assertStringNotContainsString('Initializing Sputnik project', $output);
    }

    public function testAReservedNameSkipsOnlyThatTaskAndKeepsTheCliUsable(): void
    {
        // This used to abort the whole CLI: no task ran, not even `list`.
        $this->scaffoldProject([
            'list' => <<<'PHP'
                #[Task(name: 'list', description: 'Collides with a built-in')]
                final class ListTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        return TaskResult::success();
                    }
                }
                PHP,
            'deploy' => <<<'PHP'
                #[Task(name: 'deploy', description: 'Unrelated to the collision')]
                final class DeployTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        $ctx->writeln('deploy ran');

                        return TaskResult::success();
                    }
                }
                PHP,
        ]);

        $listed = $this->sputnik(['list'], $this->tempDir);
        $listedOutput = $listed->getOutput() . $listed->getErrorOutput();

        $this->assertSame(0, $listed->getExitCode());
        $this->assertStringContainsString('Skipped task', $listedOutput);
        $this->assertStringContainsString('rename', $listedOutput);

        $unrelated = $this->sputnik(['deploy'], $this->tempDir);

        $this->assertSame(0, $unrelated->getExitCode());
        $this->assertStringContainsString('deploy ran', $unrelated->getOutput());
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
                executor: [env]
            NEON);

        $this->writeTask('container', <<<'PHP'
            #[Task(name: 'container:hello', description: 'Runs in container', environment: 'container')]
            final class ContainerTask implements TaskInterface
            {
                public function __invoke(TaskContext $ctx): TaskResult
                {
                    $ctx->exec(['echo', 'from-container']);
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
                executor: ["false"]
            NEON);

        $this->writeTask('hostonly', <<<'PHP'
            #[Task(name: 'host:hello', description: 'Runs on host', environment: 'host')]
            final class HostonlyTask implements TaskInterface
            {
                public function __invoke(TaskContext $ctx): TaskResult
                {
                    $ctx->exec(['echo', 'from-host']);
                    return TaskResult::success();
                }
            }
            PHP);

        $result = $this->sputnik(['host:hello'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('from-host', $result->getOutput());
    }

    public function testContainerTaskWithoutExecutorFails(): void
    {
        $this->scaffoldProject([
            'noexec' => <<<'PHP'
                #[Task(name: 'container:fail', description: 'No executor', environment: 'container')]
                final class NoexecTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        $ctx->exec(['echo', 'should not run']);
                        return TaskResult::success();
                    }
                }
                PHP,
        ]);

        $result = $this->sputnik(['container:fail'], $this->tempDir);

        $this->assertNotSame(0, $result->getExitCode());
        $this->assertStringContainsString('executor', $result->getOutput());
    }

    public function testExecKeepsAnArgumentWithShellMetacharactersIntact(): void
    {
        $this->scaffoldConfig(<<<'NEON'
            tasks:
                directories:
                    - sputnik

            environment:
                executor: [env]
            NEON);

        // The regression this primitive exists for: through a string command
        // the host shell would split at the semicolon and run the second half
        // outside the executor.
        $this->writeTask('meta', <<<'PHP'
            #[Task(name: 'meta', description: 'Argument with metacharacters', environment: 'container')]
            final class MetaTask implements TaskInterface
            {
                public function __invoke(TaskContext $ctx): TaskResult
                {
                    $result = $ctx->exec(['printf', '[%s]', 'a; echo pwned']);
                    $ctx->writeln($result->getOutput());

                    return TaskResult::success();
                }
            }
            PHP);

        $result = $this->sputnik(['meta'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        // One argument, so printf formats all of it. Split at the semicolon it
        // would have printed "[a]" and run the rest as a separate command.
        $this->assertStringContainsString('[a; echo pwned]', $result->getOutput());
        $this->assertStringNotContainsString('[a]', $result->getOutput());
    }

    public function testAListenerCommandIsVisibleOnAContextSwitch(): void
    {
        $this->scaffoldConfig(<<<'NEON'
            tasks:
                directories:
                    - sputnik

            contexts:
                dev:
                    description: Dev
                prod:
                    description: Prod

            defaults:
                context: dev
            NEON);

        // A listener runs outside any task, so before the output channel
        // existed its command produced no output at all - which is why
        // listeners in the field fell back to echo.
        $listenerDir = $this->tempDir . '/sputnik';
        if (!is_dir($listenerDir)) {
            mkdir($listenerDir, 0755, true);
        }

        file_put_contents($listenerDir . '/NoisyListener.php', <<<'PHP'
            <?php
            declare(strict_types=1);

            use Sputnik\Attribute\AsListener;
            use Sputnik\Event\ContextSwitchedEvent;
            use Sputnik\Executor\ExecutorInterface;

            #[AsListener(event: ContextSwitchedEvent::class, environment: 'host')]
            final class NoisyListener
            {
                public function __construct(private readonly ExecutorInterface $executor)
                {
                }

                public function __invoke(ContextSwitchedEvent $event): void
                {
                    $this->executor->execute(['echo', 'FROM-LISTENER-SHELL']);
                }
            }
            PHP);

        $result = $this->sputnik(['context:switch', 'prod'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('FROM-LISTENER-SHELL', $result->getOutput());
    }

    // ── Secret masking ──────────────────────────────────────────

    public function testSecretIsMaskedInOutput(): void
    {
        $this->scaffoldConfig(<<<'NEON'
            tasks:
                directories:
                    - sputnik

            variables:
                secrets:
                    apiToken: ghp_abcdefghij
            NEON);

        $this->writeTask('leaky', <<<'PHP'
            #[Task(name: 'leaky', description: 'Prints a secret')]
            final class LeakyTask implements TaskInterface
            {
                public function __invoke(TaskContext $ctx): TaskResult
                {
                    $ctx->shell('echo {{! apiToken }}');

                    return TaskResult::success();
                }
            }
            PHP);

        $result = $this->sputnik(['leaky'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringNotContainsString('ghp_abcdefghij', $result->getOutput());
        $this->assertStringNotContainsString('ghp_abcdefghij', $result->getErrorOutput());
        $this->assertStringContainsString('***', $result->getOutput());
    }

    public function testFailingCommandWithSecretIsMaskedOnBothChannels(): void
    {
        $this->scaffoldConfig(<<<'NEON'
            tasks:
                directories:
                    - sputnik

            variables:
                secrets:
                    apiToken: ghp_abcdefghij
            NEON);

        $this->writeTask('leaky', <<<'PHP'
            #[Task(name: 'leaky', description: 'Fails while carrying a secret')]
            final class LeakyTask implements TaskInterface
            {
                public function __invoke(TaskContext $ctx): TaskResult
                {
                    $ctx->shell('echo {{! apiToken }} >&2 && exit 7')->assertSuccess();

                    return TaskResult::success();
                }
            }
            PHP);

        $result = $this->sputnik(['leaky'], $this->tempDir);

        $this->assertNotSame(0, $result->getExitCode());
        $output = $result->getOutput();
        $errorOutput = $result->getErrorOutput();
        $this->assertStringNotContainsString('ghp_abcdefghij', $output);
        $this->assertStringNotContainsString('ghp_abcdefghij', $errorOutput);
        $this->assertStringContainsString('***', $output . $errorOutput);
    }

    // ── Helpers ─────────────────────────────────────────────────

    public function testWorkingDirIsAlsoTheDirectoryTaskCodeSeesFor(): void
    {
        // exec() has always run in the project root while PHP file I/O in the
        // task resolved against the caller's cwd, so a task doing is_dir() or
        // file_get_contents() on a relative path needed a wrapper that cd'd
        // first. Both sides agree now.
        $this->scaffoldProject([
            'io' => <<<'PHP'
                #[Task(name: 'io', description: 'relative file access')]
                final class IoTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        $ctx->writeln('php=' . var_export(file_exists('.sputnik.dist.neon'), true));
                        $ctx->writeln('cwd=' . (getcwd() === $ctx->getWorkingDir() ? 'same' : 'differs'));

                        return TaskResult::success();
                    }
                }
                PHP,
        ]);

        // Run from somewhere else entirely, addressing the project by option.
        $result = $this->sputnik(['--working-dir=' . $this->tempDir, 'io'], sys_get_temp_dir());
        $output = $result->getOutput() . $result->getErrorOutput();

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('php=true', $output);
        $this->assertStringContainsString('cwd=same', $output);
    }

    public function testARelativeWorkingDirStillResolves(): void
    {
        $this->scaffoldProject([
            'io' => <<<'PHP'
                #[Task(name: 'io', description: 'relative file access')]
                final class IoTask implements TaskInterface
                {
                    public function __invoke(TaskContext $ctx): TaskResult
                    {
                        $ctx->writeln('php=' . var_export(file_exists('.sputnik.dist.neon'), true));

                        return TaskResult::success();
                    }
                }
                PHP,
        ]);

        // Relative to the caller: resolving it has to happen before the chdir,
        // or the config path would be looked up inside itself.
        $result = $this->sputnik(['--working-dir=' . basename($this->tempDir), 'io'], \dirname($this->tempDir));

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringContainsString('php=true', $result->getOutput());
    }

    public function testAMissingWorkingDirSaysSoInsteadOfFailingOnItsCache(): void
    {
        $result = $this->sputnik(['--working-dir=/does/not/exist', 'list']);
        $output = $result->getOutput() . $result->getErrorOutput();

        $this->assertNotSame(0, $result->getExitCode());
        $this->assertStringContainsString('/does/not/exist', $output);
        $this->assertStringNotContainsString('mkdir', $output, 'The cache is a symptom, not the problem');
    }

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
