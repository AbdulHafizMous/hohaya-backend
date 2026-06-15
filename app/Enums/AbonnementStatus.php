<?php

namespace App\Enums;

enum AbonnementStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case ACTIF      = 'actif';
    case EXPIRE     = 'expiré';
    case ANNULE     = 'annulé';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}