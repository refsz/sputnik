<?php

declare(strict_types=1);

namespace Sputnik\Support;

/**
 * Finds the directory a Sputnik project lives in.
 *
 * The config file is the project's identity, the way composer.json or .git marks
 * a root, so it is searched for upwards from where you are. That is what makes a
 * call from a subdirectory work, and what keeps the container cache and the
 * persisted context in one place instead of appearing next to every caller.
 */
final class ProjectLocator
{
    private const array CONFIG_FILES = ['.sputnik.dist.neon', '.sputnik.neon'];

    /**
     * The nearest directory at or above $start holding a config file, or null
     * when there is no project - in which case nothing may be persisted.
     */
    public static function locate(string $start): ?string
    {
        $directory = realpath($start);

        if ($directory === false) {
            return null;
        }

        while (true) {
            foreach (self::CONFIG_FILES as $file) {
                if (is_file($directory . '/' . $file)) {
                    return $directory;
                }
            }

            $parent = \dirname($directory);

            if ($parent === $directory) {
                return null;
            }

            $directory = $parent;
        }
    }
}
