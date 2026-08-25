<?php

declare(strict_types=1);

namespace Sputnik\Console;

use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskMetadata;

/**
 * Puts the `--` in front of the arguments a pass-through task forwards.
 *
 * A task wrapping another tool - drush, composer, npm - has to receive `-l
 * default` or `--uri=…` untouched. Without a separator Symfony rejects the ones
 * it does not know and, worse, silently consumes the ones it does: `-v` never
 * reaches the tool and `--version` prints Sputnik's own version instead of
 * running the task. `--` is what a user would have to type for every such call,
 * and nothing in the CLI teaches it.
 */
final class PassthroughArguments
{
    /**
     * Commands that take a task name as their first argument, so the name to
     * look at is one token further along.
     */
    private const array TASK_RUNNERS = ['run'];

    /**
     * @param list<string> $argv
     *
     * @return list<string>
     */
    public static function insertSeparator(array $argv, TaskDiscovery $discovery): array
    {
        $position = self::taskNamePosition($argv);

        if ($position === null) {
            return $argv;
        }

        $task = $discovery->getTask($argv[$position]);

        if (!$task instanceof TaskMetadata || !$task->isPassthrough()) {
            return $argv;
        }

        // Already separated by hand, or nothing follows to separate.
        $rest = \array_slice($argv, $position + 1);

        if ($rest === [] || \in_array('--', $rest, true)) {
            return $argv;
        }

        array_splice($argv, $position + 1, 0, ['--']);

        return $argv;
    }

    /**
     * The index of the task name: the first argument that is not an option, and
     * the one after it when that names a command taking a task.
     *
     * @param list<string> $argv
     */
    private static function taskNamePosition(array $argv): ?int
    {
        // Element 0 is the script itself.
        for ($i = 1, $count = \count($argv); $i < $count; ++$i) {
            $argument = $argv[$i];

            if ($argument === '--') {
                return null;
            }

            if (str_starts_with($argument, '-')) {
                continue;
            }

            if (\in_array($argument, self::TASK_RUNNERS, true)) {
                return isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-') ? $i + 1 : null;
            }

            return $i;
        }

        return null;
    }
}
