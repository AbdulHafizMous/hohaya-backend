<?php

namespace App\Enums;

enum PaiementStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case SUCCES     = 'succès';
    case ECHOUE     = 'échoué';
    case REMBOURSE  = 'remboursé';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}