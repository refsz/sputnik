<?php

declare(strict_types=1);

namespace Sputnik\Console;

use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

trait TaskExecutionTrait
{
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

    abstract private function getTaskRunner(): TaskRunner;

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

        $result = $this->getTaskRunner()->run(
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

    private function createSputnikOutput(OutputInterface $output): SputnikOutput
    {
        $app = $this->getApplication();
        $configFile = $app instanceof Application ? $app->getConfigFile() : '';
        $version = $app instanceof \Symfony\Component\Console\Application ? $app->getVersion() : '0.0.0';

        return new SputnikOutput(
            $output,
            $version,
            $configFile,
            $this->getTaskRunner()->getContextName(),
        );
    }
}
