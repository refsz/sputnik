<?php

declare(strict_types=1);

namespace Sputnik;

use Sputnik\Console\Application;
use Sputnik\Console\RuntimeVariableParser;
use Sputnik\Console\SputnikOutput;
use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dynamic command wrapper for tasks.
 * Allows running tasks directly: `sputnik db:migrate` instead of `sputnik run db:migrate`.
 */
final class TaskCommand extends Command implements SignalableCommandInterface
{
    public function __construct(
        private readonly TaskMetadata $metadata,
        private readonly TaskRunner $taskRunner,
    ) {
        parent::__construct($metadata->getName());
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
        // Suggest choices for options that define them
        foreach ($this->metadata->options as $option) {
            if ($option->choices !== [] && $input->mustSuggestOptionValuesFor($option->name)) {
                $suggestions->suggestValues(array_values(array_map(strval(...), $option->choices)));

                return;
            }
        }
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->metadata->getDescription())
            ->setAliases($this->metadata->getAliases());

        // Add arguments from metadata
        foreach ($this->metadata->arguments as $argument) {
            $mode = $argument->required
                ? InputArgument::REQUIRED
                : InputArgument::OPTIONAL;

            if ($argument->isArray) {
                $mode |= InputArgument::IS_ARRAY;
            }

            $this->addArgument(
                $argument->name,
                $mode,
                $argument->description,
                $argument->required ? null : $argument->default,
            );
        }

        // Add options from metadata
        foreach ($this->metadata->options as $option) {
            $mode = $option->default === false
                ? InputOption::VALUE_NONE
                : ($option->required ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL);

            $this->addOption(
                $option->name,
                $option->shortcut,
                $mode,
                $option->description,
                $mode === InputOption::VALUE_NONE ? null : $option->default,
            );
        }

        // Add global -D option for runtime variables
        $this->addOption(
            'define',
            'D',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Define a runtime variable (NAME=value)',
            [],
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arguments = [];
        foreach ($this->metadata->arguments as $argument) {
            $value = $input->getArgument($argument->name);
            if ($value !== null) {
                $arguments[$argument->name] = $value;
            }
        }

        $options = [];
        foreach ($this->metadata->options as $option) {
            $value = $input->getOption($option->name);
            if ($value !== null && $value !== $option->default) {
                $options[$option->name] = $value;
            }
        }

        $runtimeVariables = RuntimeVariableParser::parse($input->getOption('define'));

        try {
            return $this->executeTask($this->metadata, $arguments, $options, $output, $runtimeVariables);
        } catch (\Throwable $throwable) {
            $io = new SymfonyStyle($input, $output);
            $io->error('Task execution failed: ' . $throwable->getMessage());
            if ($output->isVerbose()) {
                $io->text($throwable->getTraceAsString());
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
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $options
     * @param array<string, mixed> $runtimeVariables
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
}
