<?php

declare(strict_types=1);

namespace Sputnik\Console;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * PSR-3 Logger that writes to Symfony Console output.
 *
 * Maps log levels to output verbosity and colors:
 * - emergency, alert, critical, error -> always shown, red
 * - warning -> shown with -v, yellow
 * - notice, info -> shown with -v, green/default
 * - debug -> shown with -vv, gray
 */
final class ConsoleLogger extends AbstractLogger
{
    private const LEVEL_VERBOSITY = [
        LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
        LogLevel::WARNING => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::INFO => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::DEBUG => OutputInterface::VERBOSITY_VERY_VERBOSE,
    ];

    private const LEVEL_FORMATS = [
        LogLevel::EMERGENCY => '<error>',
        LogLevel::ALERT => '<error>',
        LogLevel::CRITICAL => '<error>',
        LogLevel::ERROR => '<error>',
        LogLevel::WARNING => '<comment>',
        LogLevel::NOTICE => '<info>',
        LogLevel::INFO => '<info>',
        LogLevel::DEBUG => '<fg=gray>',
    ];

    private const LEVEL_CLOSE_FORMATS = [
        LogLevel::EMERGENCY => '</error>',
        LogLevel::ALERT => '</error>',
        LogLevel::CRITICAL => '</error>',
        LogLevel::ERROR => '</error>',
        LogLevel::WARNING => '</comment>',
        LogLevel::NOTICE => '</info>',
        LogLevel::INFO => '</info>',
        LogLevel::DEBUG => '</>',
    ];

    public function __construct(
        private readonly OutputInterface $output,
    ) {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $level = (string) $level;

        // Check verbosity
        $verbosity = self::LEVEL_VERBOSITY[$level] ?? OutputInterface::VERBOSITY_NORMAL;
        if ($this->output->getVerbosity() < $verbosity) {
            return;
        }

        // Interpolate context into message
        $message = $this->interpolate((string) $message, $context);

        // Format with colors
        $format = self::LEVEL_FORMATS[$level] ?? '';
        $closeFormat = self::LEVEL_CLOSE_FORMATS[$level] ?? '';

        // Add level prefix for warnings and errors
        $prefix = match ($level) {
            LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL => '[CRITICAL] ',
            LogLevel::ERROR => '[ERROR] ',
            LogLevel::WARNING => '[WARNING] ',
            LogLevel::DEBUG => '[DEBUG] ',
            default => '',
        };

        $this->output->writeln($format . $prefix . $message . $closeFormat);
    }

    /**
     * Interpolate context values into message placeholders.
     *
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (\is_string($value) || (\is_object($value) && method_exists($value, '__toString'))) {
                $replacements['{' . $key . '}'] = (string) $value;
            } elseif (\is_scalar($value)) {
                $replacements['{' . $key . '}'] = var_export($value, true);
            }
        }

        return strtr($message, $replacements);
    }
}
