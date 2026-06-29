<?php

namespace App\Enums;

enum SignalementType: string
{
    case PROPRIETAIRE = 'proprietaire';
    case UTILISATEUR  = 'utilisateur';
    case ANNONCE      = 'annonce';      // signaler une fausse annonce
    case FRAUDE       = 'fraude';
    case AUTRE        = 'autre';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}