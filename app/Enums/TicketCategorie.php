<?php

namespace App\Enums;

enum TicketCategorie: string
{
    case TECHNIQUE   = 'technique';
    case FACTURATION = 'facturation';
    case LOGEMENT    = 'logement';     // spécifique au contexte
    case FRAUDE      = 'fraude';       // important en Afrique
    case GENERAL     = 'general';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}