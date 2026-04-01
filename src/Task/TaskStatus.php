<?php

declare(strict_types=1);

namespace Sputnik\Task;

enum TaskStatus: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Skipped = 'skipped';
}
