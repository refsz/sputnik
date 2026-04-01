<?php

declare(strict_types=1);

namespace Sputnik\Console\Command;

use Sputnik\Context\ContextManager;
use Sputnik\Context\ContextNotFoundException;
use Sputnik\Event\ContextSwitchedEvent;
use Sputnik\Template\TemplateEngine;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'context:switch',
    description: 'Switch to a different context',
    aliases: ['switch', 'use'],
)]
final class ContextSwitchCommand extends Command
{
    public function __construct(
        private readonly ContextManager $contextManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?TemplateEngine $templateEngine = null,
    ) {
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('context')) {
            $suggestions->suggestValues(array_values($this->contextManager->getAvailableContexts()));
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('context', InputArgument::REQUIRED, 'Context name to switch to')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command switches to a different context:

                  <info>%command.full_name% production</info>

                This will:
                - Update the current context
                - Persist the context for future runs
                - Trigger ContextSwitchedEvent (regenerates templates by default)

                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $contextName = $input->getArgument('context');

        try {
            $result = $this->contextManager->switchTo($contextName);

            if ($result['previous'] === $result['new']) {
                $io->note('Already in context: ' . $contextName);

                return Command::SUCCESS;
            }

            // Set up interactive overwrite confirmation for template rendering
            if ($this->templateEngine instanceof TemplateEngine) {
                $this->templateEngine->setConfirmOverwrite(
                    static fn (string $path): bool => $io->confirm(\sprintf('Overwrite %s?', $path), true),
                );
            }

            // Dispatch event
            $event = new ContextSwitchedEvent($result['previous'], $result['new']);
            $this->eventDispatcher->dispatch($event);

            $io->success(\sprintf("Switched from '%s' to '%s'", $result['previous'], $result['new']));

            // Show context description if available
            $description = $this->contextManager->getContextDescription($contextName);
            if ($description !== null) {
                $io->text('Description: ' . $description);
            }

            return Command::SUCCESS;
        } catch (ContextNotFoundException $contextNotFoundException) {
            $io->error($contextNotFoundException->getMessage());

            if ($contextNotFoundException->available !== []) {
                $io->text('Available contexts:');
                foreach ($contextNotFoundException->available as $available) {
                    $desc = $this->contextManager->getContextDescription($available);
                    $io->text(\sprintf('  - %s%s', $available, $desc !== null ? \sprintf(' (%s)', $desc) : ''));
                }
            }

            return Command::FAILURE;
        }
    }
}
