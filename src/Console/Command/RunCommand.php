<?php

declare(strict_types=1);

namespace Sputnik\Console\Command;

use Sputnik\Console\RuntimeVariableParser;
use Sputnik\Console\TaskExecutionTrait;
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
    use TaskExecutionTrait;

    public function __construct(
        private readonly TaskDiscovery $discovery,
        private readonly TaskRunner $taskRunner,
    ) {
        parent::__construct();
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
        $runtimeVariables = RuntimeVariableParser::parse($input->getOption('define'));

        try {
            $metadata = $this->discovery->getTask($taskName);
            if (!$metadata instanceof TaskMetadata) {
                throw TaskNotFoundException::forTask($taskName);
            }

            [$arguments, $options] = $this->parseTaskArgs($taskArgs, $metadata);

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

    /**
     * @param array<string> $args
     *
     * @return array{array<int, string>, array<string, string|true>}
     */
    private function parseTaskArgs(array $args, TaskMetadata $metadata): array
    {
        $arguments = [];
        $options = [];
        $argIndex = 0;

        // Build lookup maps from metadata
        $valueOptions = [];
        $shortcutMap = [];
        foreach ($metadata->options as $option) {
            if ($option->default !== false) {
                $valueOptions[$option->name] = true;
            }
            if ($option->shortcut !== null) {
                $shortcutMap[$option->shortcut] = $option->name;
            }
        }

        for ($i = 0; $i < \count($args); ++$i) {
            $arg = $args[$i];

            if (str_starts_with($arg, '--')) {
                $option = substr($arg, 2);
                if (str_contains($option, '=')) {
                    [$name, $value] = explode('=', $option, 2);
                    $options[$name] = $value;
                } elseif (isset($valueOptions[$option]) && isset($args[$i + 1])) {
                    $options[$option] = $args[++$i];
                } else {
                    $options[$option] = true;
                }
            } elseif (str_starts_with($arg, '-')) {
                $shortcut = substr($arg, 1);
                $name = $shortcutMap[$shortcut] ?? $shortcut;
                if (isset($valueOptions[$name]) && isset($args[$i + 1])) {
                    $options[$name] = $args[++$i];
                } else {
                    $options[$name] = true;
                }
            } else {
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

    private function getTaskRunner(): TaskRunner
    {
        return $this->taskRunner;
    }
}
