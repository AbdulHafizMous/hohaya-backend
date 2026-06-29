<?php

namespace App\Enums;

enum PaiementType: string
{
    case ABONNEMENT      = 'abonnement';       // Paiement abonnement propriétaire
    case DEBLOCAGE       = 'deblocage';        // Paiement pour débloquer contact

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}