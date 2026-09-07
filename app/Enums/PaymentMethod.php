<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transferencia',
            self::Cash => 'Efectivo',
        };
    }
}
