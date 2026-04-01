<?php

declare(strict_types=1);

namespace Sputnik\Console\Command;

use Sputnik\Exception\RuntimeException as SputnikRuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'init',
    description: 'Initialize a new Sputnik project',
)]
final class InitCommand extends Command
{
    private const CONFIG_FILE = '.sputnik.dist.neon';

    private const TASKS_DIR = 'sputnik';

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command initializes a new Sputnik project:

                  <info>%command.full_name%</info>

                This creates:
                  - .sputnik.dist.neon configuration file
                  - sputnik/ directory with an example task

                Use --force to overwrite existing files:

                  <info>%command.full_name% --force</info>

                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $cwdResult = getcwd();
        $cwd = $cwdResult !== false ? $cwdResult : throw new SputnikRuntimeException('Could not determine working directory');

        $io->title('Initializing Sputnik project');

        $created = [];
        $skipped = [];

        // Create config file
        $configPath = $cwd . '/' . self::CONFIG_FILE;
        if (!file_exists($configPath) || $force === true) {
            if (file_put_contents($configPath, $this->getConfigTemplate()) === false) {
                $io->error('Could not write ' . $configPath);

                return Command::FAILURE;
            }

            $created[] = self::CONFIG_FILE;
        } else {
            $skipped[] = self::CONFIG_FILE;
        }

        // Create tasks directory
        $tasksDir = $cwd . '/' . self::TASKS_DIR;
        if (!is_dir($tasksDir)) {
            if (!mkdir($tasksDir, 0755, true) && !is_dir($tasksDir)) {
                $io->error('Could not create directory ' . $tasksDir);

                return Command::FAILURE;
            }

            $created[] = self::TASKS_DIR . '/';
        }

        // Create example task
        $exampleTaskPath = $tasksDir . '/ExampleTask.php';
        if (!file_exists($exampleTaskPath) || $force === true) {
            if (file_put_contents($exampleTaskPath, $this->getExampleTaskTemplate()) === false) {
                $io->error('Could not write ' . $exampleTaskPath);

                return Command::FAILURE;
            }

            $created[] = self::TASKS_DIR . '/ExampleTask.php';
        } else {
            $skipped[] = self::TASKS_DIR . '/ExampleTask.php';
        }

        // Report results
        if ($created !== []) {
            $io->success('Created:');
            $io->listing($created);
        }

        if ($skipped !== []) {
            $io->note('Skipped (already exists):');
            $io->listing($skipped);
            $io->text('Use --force to overwrite');
        }

        $io->newLine();
        $io->text('Next steps:');
        $io->listing([
            'Edit <info>.sputnik.dist.neon</info> to configure your project',
            'Create tasks in <info>sputnik/</info> directory',
            'Run <info>sputnik example</info> to test the example task',
        ]);

        return Command::SUCCESS;
    }

    private function getConfigTemplate(): string
    {
        return <<<'NEON'
# Sputnik Configuration

tasks:
    directories:
        - sputnik

contexts:
    local:
        description: Local development
        variables:
            constants:
                debug: true

    staging:
        description: Staging environment
        variables:
            constants:
                debug: true

    production:
        description: Production environment
        variables:
            constants:
                debug: false

variables:
    constants:
        app_name: MyApp

defaults:
    context: local

NEON;
    }

    private function getExampleTaskTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Sputnik\Attribute\Task;
use Sputnik\Attribute\Option;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(
    name: 'example',
    description: 'An example task to get you started',
)]
final class ExampleTask implements TaskInterface
{
    #[Option(
        name: 'name',
        description: 'Name to greet',
        default: 'World',
    )]
    private string $name;

    public function __invoke(TaskContext $ctx): TaskResult
    {
        $name = $ctx->option('name');
        $appName = $ctx->get('app_name', 'Sputnik');
        $context = $ctx->getContextName();

        $ctx->success("Hello, {$name}!");
        $ctx->info("App: {$appName}");
        $ctx->info("Context: {$context}");

        // Example shell command (uncomment to try)
        // $result = $ctx->shell('echo "Hello from shell"');
        // $ctx->info($result->output);

        return TaskResult::success("Greeted {$name}");
    }
}

PHP;
    }
}
