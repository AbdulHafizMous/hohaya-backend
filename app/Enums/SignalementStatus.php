<?php

namespace App\Enums;

enum SignalementStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case EN_COURS   = 'en_cours';
    case TRAITE     = 'traité';
    case REJETE     = 'rejeté';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
