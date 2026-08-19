<?php

declare(strict_types=1);

namespace Sputnik\Template;

interface ValueFormatterInterface
{
    /**
     * Turn a resolved variable value into the text that replaces the placeholder.
     */
    public function format(mixed $value): string;
}
