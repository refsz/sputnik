<?php

declare(strict_types=1);

namespace Sputnik\Console\Command;

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

    private const IGNORE_FILE = '.gitignore';

    /**
     * Sputnik writes these itself: the compiled container and the persisted
     * context under .sputnik/, and .sputnik.neon as the local override of the
     * committed .sputnik.dist.neon.
     */
    private const array IGNORED_PATHS = ['/.sputnik/', '/.sputnik.neon'];

    public function __construct(private readonly string $targetDir)
    {
        parent::__construct();
    }

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

        $io->title('Initializing Sputnik project');

        $created = [];
        $skipped = [];

        // Create config file
        $configPath = $this->targetDir . '/' . self::CONFIG_FILE;
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
        $tasksDir = $this->targetDir . '/' . self::TASKS_DIR;
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

        $ignorePath = $this->targetDir . '/' . self::IGNORE_FILE;
        $hadIgnoreFile = file_exists($ignorePath);

        if (!$this->ignoreGeneratedFiles($ignorePath)) {
            $io->error('Could not write ' . $ignorePath);

            return Command::FAILURE;
        }

        if (!$hadIgnoreFile) {
            $created[] = self::IGNORE_FILE;
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

    /**
     * Add the paths Sputnik generates to .gitignore, creating the file if the
     * project has none. An existing file is only ever appended to, and only
     * with the entries it does not already have.
     */
    private function ignoreGeneratedFiles(string $path): bool
    {
        if (!file_exists($path)) {
            return file_put_contents($path, implode("\n", self::IGNORED_PATHS) . "\n") !== false;
        }

        $existing = file_get_contents($path);

        if ($existing === false) {
            return false;
        }

        $lines = preg_split('/\R/', $existing);

        if ($lines === false) {
            return false;
        }

        $missing = array_values(array_diff(self::IGNORED_PATHS, array_map(trim(...), $lines)));

        if ($missing === []) {
            return true;
        }

        $leadingNewline = str_ends_with($existing, "\n") ? '' : "\n";
        $block = $leadingNewline . "\n# Sputnik\n" . implode("\n", $missing) . "\n";

        return file_put_contents($path, $block, \FILE_APPEND) !== false;
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

    # Values Sputnik must never print. Every occurrence is replaced with *** in
    # echoed commands, command output and log lines.
    # secrets:
    #     apiToken:
    #         type: command
    #         command: "pass show project/api"

# Route tasks marked environment: 'container' through a container executor.
# environment:
#     executor: [ddev, exec]

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
        $ctx->writeln("App: {$appName}");
        $ctx->writeln("Context: {$context}");

        // info(), warning() and error() go to the log, which is only shown
        // with -v. Use writeln() or success() for output the user should see.
        $ctx->info('This line needs -v to appear');

        // Run a program without a shell (uncomment to try). Arguments are
        // passed through as they are, so nothing needs escaping.
        // $result = $ctx->exec(['echo', 'Hello from {{ app_name }}']);
        // $ctx->writeln($result->getOutput());

        return TaskResult::success("Greeted {$name}");
    }
}

PHP;
    }
}
