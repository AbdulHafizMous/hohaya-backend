<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case DISPONIBLE = 'disponible';
    case LOUE       = 'loué';
    case SUSPENDU   = 'suspendu';  // suspendu par l'admin

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}