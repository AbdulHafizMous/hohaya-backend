<?php

namespace App\Enums;

enum TicketStatus: string
{
    case OUVERT  = 'ouvert';
    case EN_COURS = 'en_cours';
    case FERME   = 'fermé';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    
    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}