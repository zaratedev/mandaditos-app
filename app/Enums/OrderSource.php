<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderSource: string
{
    case Manual = 'manual';
    case Portal = 'portal';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Portal => 'Portal',
        };
    }
}
