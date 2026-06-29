<?php

namespace App\Enums;

enum PropertyZone: string
{
    // Extérieur
    case FACADE_EXTERIEURE  = 'facade_exterieure';
    case ENTREE             = 'entree';
    case COUR               = 'cour';
    case JARDIN             = 'jardin';
    case TERRASSE           = 'terrasse';
    case BALCON             = 'balcon';
    case TOITURE            = 'toiture';
    case PARKING            = 'parking';
    case PORTAIL            = 'portail';

    // Pièces principales
    case SALON              = 'salon';
    case SALLE_A_MANGER     = 'salle_a_manger';
    case CUISINE            = 'cuisine';
    case COULOIR            = 'couloir';

    // Chambres
    case CHAMBRE_PRINCIPALE = 'chambre_principale';
    case CHAMBRE_2          = 'chambre_2';
    case CHAMBRE_3          = 'chambre_3';
    case CHAMBRE_4          = 'chambre_4';

    // Sanitaires
    case SALLE_DE_BAIN      = 'salle_de_bain';
    case DOUCHE             = 'douche';
    case TOILETTES          = 'toilettes';
    case SALLE_D_EAU        = 'salle_d_eau';

    // Annexes
    case GARAGE             = 'garage';
    case MAGASIN            = 'magasin';
    case CAVE               = 'cave';
    case BUANDERIE          = 'buanderie';
    case BUREAU             = 'bureau';
    case SALLE_DE_SPORT     = 'salle_de_sport';

    // Communs (immeuble)
    case COULOIR_COMMUN     = 'couloir_commun';
    case ESCALIERS          = 'escaliers';
    case ASCENSEUR          = 'ascenseur';
    case PISCINE            = 'piscine';

    // Générique
    case AUTRE              = 'autre';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    public static function labels(): array
    {
        return [
            self::FACADE_EXTERIEURE->value  => 'Façade extérieure',
            self::ENTREE->value             => 'Entrée',
            self::COUR->value               => 'Cour',
            self::JARDIN->value             => 'Jardin',
            self::TERRASSE->value           => 'Terrasse',
            self::BALCON->value             => 'Balcon',
            self::TOITURE->value            => 'Toiture',
            self::PARKING->value            => 'Parking',
            self::PORTAIL->value            => 'Portail',
            self::SALON->value              => 'Salon',
            self::SALLE_A_MANGER->value     => 'Salle à manger',
            self::CUISINE->value            => 'Cuisine',
            self::COULOIR->value            => 'Couloir',
            self::CHAMBRE_PRINCIPALE->value => 'Chambre principale',
            self::CHAMBRE_2->value          => 'Chambre 2',
            self::CHAMBRE_3->value          => 'Chambre 3',
            self::CHAMBRE_4->value          => 'Chambre 4',
            self::SALLE_DE_BAIN->value      => 'Salle de bain',
            self::DOUCHE->value             => 'Douche',
            self::TOILETTES->value          => 'Toilettes',
            self::SALLE_D_EAU->value        => 'Salle d\'eau',
            self::GARAGE->value             => 'Garage',
            self::MAGASIN->value            => 'Magasin / Dépôt',
            self::CAVE->value               => 'Cave',
            self::BUANDERIE->value          => 'Buanderie',
            self::BUREAU->value             => 'Bureau',
            self::SALLE_DE_SPORT->value     => 'Salle de sport',
            self::COULOIR_COMMUN->value     => 'Couloir commun',
            self::ESCALIERS->value          => 'Escaliers',
            self::ASCENSEUR->value          => 'Ascenseur',
            self::PISCINE->value            => 'Piscine',
            self::AUTRE->value              => 'Autre',
        ];
    }
}