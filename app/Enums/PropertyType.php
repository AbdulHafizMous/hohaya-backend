<?php

namespace App\Enums;

enum PropertyType: string
{
    case APPARTEMENT = 'appartement';
    case MAISON      = 'maison';
    case STUDIO      = 'studio';
    case CHAMBRE     = 'chambre';        // très courant en Afrique
    case VILLA       = 'villa';
    case DUPLEX      = 'duplex';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}