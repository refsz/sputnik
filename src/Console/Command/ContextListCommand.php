<?php

declare(strict_types=1);

namespace Sputnik\Console\Command;

use Sputnik\Context\ContextManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'context:list',
    description: 'List available contexts',
    aliases: ['contexts'],
)]
final class ContextListCommand extends Command
{
    public function __construct(
        private readonly ContextManager $contextManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $contexts = $this->contextManager->getAvailableContexts();
        $current = $this->contextManager->getCurrentContext();

        if ($contexts === []) {
            $io->warning('No contexts configured');

            return Command::SUCCESS;
        }

        $io->title('Available Contexts');

        $rows = [];
        foreach ($contexts as $contextName) {
            $isCurrent = $contextName === $current;
            $description = $this->contextManager->getContextDescription($contextName) ?? '';

            $rows[] = [
                $isCurrent ? '<info>*</info>' : ' ',
                $isCurrent ? \sprintf('<info>%s</info>', $contextName) : $contextName,
                $description,
            ];
        }

        $io->table(['', 'Context', 'Description'], $rows);

        $io->text(\sprintf('Current context: <info>%s</info>', $current));

        return Command::SUCCESS;
    }
}
