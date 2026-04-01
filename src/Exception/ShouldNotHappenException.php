<?php

declare(strict_types=1);

namespace Sputnik\Exception;

class ShouldNotHappenException extends SputnikException
{
    public function __construct(string $message = 'This should not happen. Please report this as a bug.')
    {
        parent::__construct($message);
    }
}
