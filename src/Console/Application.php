<?php

declare(strict_types=1);

namespace Sputnik\Console;

use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskMetadata;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Application extends BaseApplication
{
    public const NAME = 'Sputnik';

    public const VERSION = '0.1.0';

    private string $configFile = '';

    private ?TaskDiscovery $taskDiscovery = null;

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);
    }

    public function setConfigFile(string $configFile): void
    {
        $this->configFile = $configFile;
    }

    public function getConfigFile(): string
    {
        return $this->configFile;
    }

    public function setTaskDiscovery(TaskDiscovery $discovery): void
    {
        $this->taskDiscovery = $discovery;
    }

    public function getHelp(): string
    {
        return '';
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getCommandName($input);
        $isList = $commandName === null || $commandName === 'list';

        // Show our header instead of Symfony's "AppName version" for list
        if ($isList) {
            $output->writeln(\sprintf(
                "\xF0\x9F\x9B\xB0  <fg=green;options=bold>Sputnik v%s</> <fg=gray>│</> %s <fg=gray>│</> %s",
                self::VERSION,
                $this->configFile !== '' ? $this->configFile : 'no config',
                'PHP ' . \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION,
            ));
            $output->writeln('');
        }

        $result = parent::doRun($input, $output);

        if ($isList && $this->taskDiscovery instanceof TaskDiscovery) {
            $this->renderTaskList($output);
        }

        return $result;
    }

    private function renderTaskList(OutputInterface $output): void
    {
        if (!$this->taskDiscovery instanceof TaskDiscovery) {
            return;
        }

        $tasks = $this->taskDiscovery->discoverAll();

        $visible = [];
        foreach ($tasks as $metadata) {
            if (!$metadata->isHidden()) {
                $visible[] = $metadata;
            }
        }

        if ($visible === []) {
            return;
        }

        // Group by group attribute
        $grouped = [];
        foreach ($visible as $task) {
            $group = $task->getGroup() ?? '';
            $grouped[$group][] = $task;
        }

        // Sort tasks alphabetically within each group
        foreach ($grouped as &$groupTasks) {
            usort($groupTasks, static fn (TaskMetadata $a, TaskMetadata $b): int => $a->getName() <=> $b->getName());
        }

        unset($groupTasks);

        ksort($grouped);
        if (isset($grouped[''])) {
            $ungrouped = $grouped[''];
            unset($grouped['']);
            $grouped = ['' => $ungrouped] + $grouped;
        }

        // Calculate max width for alignment
        $maxWidth = 0;
        foreach ($visible as $task) {
            $name = $task->getName();
            $aliases = $task->getAliases();
            $aliasStr = $aliases === [] ? '' : ' [' . implode(', ', $aliases) . ']';
            $width = mb_strlen($name) + mb_strlen($aliasStr);
            if ($width > $maxWidth) {
                $maxWidth = $width;
            }
        }

        $output->writeln('');
        $output->writeln('<comment>Available tasks:</comment>');

        foreach ($grouped as $group => $groupTasks) {
            if ($group !== '') {
                $output->writeln(' <comment>' . $group . '</comment>');
            }

            foreach ($groupTasks as $task) {
                $name = $task->getName();
                $aliases = $task->getAliases();
                $aliasStr = $aliases === [] ? '' : ' [' . implode(', ', $aliases) . ']';
                $envTag = $this->getEnvironmentTag($task);

                $spacing = str_repeat(' ', $maxWidth - mb_strlen($name) - mb_strlen($aliasStr) + 2);
                $output->writeln(\sprintf(
                    '  <info>%s</info>%s%s%s%s',
                    $name,
                    $aliasStr,
                    $spacing,
                    $task->getDescription(),
                    $envTag,
                ));
            }
        }

        $output->writeln('');
    }

    private function getEnvironmentTag(TaskMetadata $task): string
    {
        $env = $task->getEnvironment();

        if ($env === null) {
            return '';
        }

        return '  <fg=gray>[' . $env . ']</>';
    }
}
