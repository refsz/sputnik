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
            if (!\is_string($contextName)) {
                $io->error('A context name is required');

                return Command::FAILURE;
            }

            if (!$this->contextManager->isValidContext($contextName)) {
                throw ContextNotFoundException::forContext(
                    $contextName,
                    $this->contextManager->getAvailableContexts(),
                );
            }

            $previous = $this->contextManager->getCurrentContext();

            if ($previous === $contextName) {
                $io->note('Already in context: ' . $contextName);

                return Command::SUCCESS;
            }

            // Set up interactive overwrite confirmation for template rendering
            if ($this->templateEngine instanceof TemplateEngine) {
                $this->templateEngine->setConfirmOverwrite(
                    static fn (string $path): bool => $io->confirm(\sprintf('Overwrite %s?', $path), true),
                );
            }

            // Listeners first, then the switch is written down. Switching a
            // context means preparing the project for it - regenerating
            // templates, reinstalling dependencies - and a switch whose
            // preparation failed should not outlive the process. Persisting
            // first left state.json naming the new context while the work for it
            // had not happened, and the next command ran on a half-prepared
            // project.
            //
            // Listeners do not need the persisted value: they read the context
            // from the event, and SwitchContextOnServices (priority 100) puts the
            // resolver and the template engine on the new one before any other
            // listener runs.
            $event = new ContextSwitchedEvent($previous, $contextName);
            $this->eventDispatcher->dispatch($event);

            $this->contextManager->switchTo($contextName);

            $io->success(\sprintf("Switched from '%s' to '%s'", $previous, $contextName));

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
