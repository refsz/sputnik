<?php

declare(strict_types=1);

namespace Sputnik\Console\Command;

use Sputnik\Console\Application;
use Sputnik\Console\RuntimeVariableParser;
use Sputnik\Console\SputnikOutput;
use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskNotFoundException;
use Sputnik\Task\TaskRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'run',
    description: 'Run a task',
)]
final class RunCommand extends Command implements SignalableCommandInterface
{
    public function __construct(
        private readonly TaskDiscovery $discovery,
        private readonly TaskRunner $taskRunner,
    ) {
        parent::__construct();
    }

    /**
     * @return list<int>
     */
    public function getSubscribedSignals(): array
    {
        return [\SIGINT, \SIGTERM];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int
    {
        return Command::FAILURE;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('task')) {
            $suggestions->suggestValues(array_values($this->discovery->getTaskNames()));
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('task', InputArgument::REQUIRED, 'The task name to run')
            ->addArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Task arguments')
            ->addOption(
                'define',
                'D',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Define a runtime variable (NAME=value)',
                [],
            )
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command runs a specified task:

                  <info>%command.full_name% db:migrate</info>

                You can pass arguments and options to the task:

                  <info>%command.full_name% deploy production --force</info>

                You can define runtime variables with -D:

                  <info>%command.full_name% deploy -D DB_HOST=localhost -D DEBUG=true</info>

                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $taskName = $input->getArgument('task');
        $taskArgs = $input->getArgument('args') ?? [];
        [$arguments, $options] = $this->parseTaskArgs($taskArgs);
        $runtimeVariables = RuntimeVariableParser::parse($input->getOption('define'));

        try {
            $metadata = $this->discovery->getTask($taskName);
            if (!$metadata instanceof TaskMetadata) {
                throw TaskNotFoundException::forTask($taskName);
            }

            return $this->executeTask($metadata, $arguments, $options, $output, $runtimeVariables);
        } catch (TaskNotFoundException $e) {
            $io = new SymfonyStyle($input, $output);
            $io->error($e->getMessage());
            $this->suggestSimilarTasks($io, $taskName);

            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io = new SymfonyStyle($input, $output);
            $io->error('Task execution failed: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function createSputnikOutput(OutputInterface $output): SputnikOutput
    {
        $app = $this->getApplication();
        $configFile = $app instanceof Application ? $app->getConfigFile() : '';
        $version = $app instanceof \Symfony\Component\Console\Application ? $app->getVersion() : '0.0.0';

        return new SputnikOutput(
            $output,
            $version,
            $configFile,
            $this->taskRunner->getContextName(),
        );
    }

    /**
     * @param array<int|string, mixed> $arguments
     * @param array<string, mixed>     $options
     * @param array<string, mixed>     $runtimeVariables
     */
    private function executeTask(
        TaskMetadata $metadata,
        array $arguments,
        array $options,
        OutputInterface $output,
        array $runtimeVariables,
    ): int {
        $sputnikOutput = $this->createSputnikOutput($output);
        $sputnikOutput->header();
        $sputnikOutput->taskStart($metadata->getName(), $metadata->getDescription());

        $result = $this->taskRunner->run(
            $metadata->getName(),
            $arguments,
            $options,
            $output,
            $runtimeVariables,
            $sputnikOutput,
        );

        if ($result->isSuccessful()) {
            $sputnikOutput->success($result->duration, $result->message);

            return Command::SUCCESS;
        }

        if ($result->isSkipped()) {
            $sputnikOutput->skipped($result->message ?? 'No reason given');

            return Command::SUCCESS;
        }

        $sputnikOutput->failure($result->message ?? 'Unknown error');

        return $result->exitCode ?? Command::FAILURE;
    }

    /**
     * Parse task arguments into separate arguments and options arrays.
     *
     * @param array<string> $args
     *
     * @return array{array<int, string>, array<string, string|true>}
     */
    private function parseTaskArgs(array $args): array
    {
        $arguments = [];
        $options = [];
        $argIndex = 0;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                // Long option
                $option = substr($arg, 2);
                if (str_contains($option, '=')) {
                    [$name, $value] = explode('=', $option, 2);
                    $options[$name] = $value;
                } else {
                    $options[$option] = true;
                }
            } elseif (str_starts_with($arg, '-')) {
                // Short option
                $option = substr($arg, 1);
                $options[$option] = true;
            } else {
                // Positional argument
                $arguments[$argIndex] = $arg;
                ++$argIndex;
            }
        }

        return [$arguments, $options];
    }

    private function suggestSimilarTasks(SymfonyStyle $io, string $taskName): void
    {
        $allTasks = $this->discovery->getTaskNames();
        $suggestions = [];

        foreach ($allTasks as $name) {
            $distance = levenshtein($taskName, $name);
            if ($distance <= 3) {
                $suggestions[$name] = $distance;
            }
        }

        if ($suggestions !== []) {
            asort($suggestions);
            $io->text('Did you mean one of these?');
            foreach (array_keys($suggestions) as $suggestion) {
                $io->text('  - ' . $suggestion);
            }
        }
    }
}
