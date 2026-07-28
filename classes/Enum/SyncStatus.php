<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Enum;

enum SyncStatus: string
{
    case Idle = 'idle';
    case Running = 'running';
    case Success = 'success';
    case Skipped = 'skipped';
    case Error = 'error';
    case Partial = 'partial';
}
