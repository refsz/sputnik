<?php

declare(strict_types=1);

namespace Sputnik\Support;

final class SourceFingerprint
{
    /**
     * Fingerprint every PHP file below a directory by path and modification
     * time, cheap enough to run on every boot because it only stats files.
     * Contents are not read: an upgrade rewrites the files, and a checkout
     * stamps them, so the modification time already moves with the code.
     */
    public static function ofDirectory(string $directory): string
    {
        if (!is_dir($directory)) {
            return md5('');
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname() . ':' . $file->getMTime();
        }

        sort($files);

        return md5(implode('|', $files));
    }
}
