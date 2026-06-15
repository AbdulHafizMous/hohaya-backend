<?php

namespace Database\Seeders;

use App\Enums\AbonnementType;
use App\Models\TarifAbonnement;
use Illuminate\Database\Seeder;

class TarifsAbonnementSeeder extends Seeder
{
    public function run(): void
    {
        $tarifs = [
            [
                'type'        => AbonnementType::MENSUEL->value,
                'montant'     => 5000,
                'devise'      => 'XOF',
                'is_actif'    => true,
                'description' => 'Abonnement mensuel — 1 mois d\'accès',
            ],
            [
                'type'        => AbonnementType::TRIMESTRIEL->value,
                'montant'     => 12000,
                'devise'      => 'XOF',
                'is_actif'    => true,
                'description' => 'Abonnement trimestriel — 3 mois d\'accès (économisez 20%)',
            ],
            [
                'type'        => AbonnementType::SEMESTRIEL->value,
                'montant'     => 20000,
                'devise'      => 'XOF',
                'is_actif'    => true,
                'description' => 'Abonnement semestriel — 6 mois d\'accès (économisez 33%)',
            ],
            [
                'type'        => AbonnementType::ANNUEL->value,
                'montant'     => 35000,
                'devise'      => 'XOF',
                'is_actif'    => true,
                'description' => 'Abonnement annuel — 12 mois d\'accès (économisez 42%)',
            ],
        ];

        foreach ($tarifs as $tarif) {
            TarifAbonnement::updateOrCreate(['type' => $tarif['type']], $tarif);
        }

        $this->command->info('~ Tarifs abonnements créés.');
    }
}