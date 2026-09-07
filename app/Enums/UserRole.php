<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Courier = 'courier';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Courier => 'Repartidor',
        };
    }
}
