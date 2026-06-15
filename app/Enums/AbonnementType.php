<?php

namespace App\Enums;

enum AbonnementType: string
{
    case MENSUEL    = 'mensuel';
    case TRIMESTRIEL = 'trimestriel';
    case SEMESTRIEL = 'semestriel';
    case ANNUEL     = 'annuel';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    public function dureeEnJours(): int
    {
        return match($this) {
            self::MENSUEL     => 30,
            self::TRIMESTRIEL => 90,
            self::SEMESTRIEL  => 180,
            self::ANNUEL      => 365,
        };
    }

    public function label(): string
    {
        return match($this) {
            self::MENSUEL     => '1 mois',
            self::TRIMESTRIEL => '3 mois',
            self::SEMESTRIEL  => '6 mois',
            self::ANNUEL      => '1 an',
        };
    }
}