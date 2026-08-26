<?php

use App\Enums\PaiementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite ne supporte pas ENUM ni ALTER ... MODIFY : la colonne y est déjà un simple
        // TEXT sans contrainte, donc rien à faire pour ajouter une nouvelle valeur autorisée.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $values = implode("','", PaiementType::values());
        DB::statement("ALTER TABLE paiements MODIFY type ENUM('{$values}') NOT NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $values = implode("','", array_filter(PaiementType::values(), fn($v) => $v !== PaiementType::RESERVATION->value));
        DB::statement("ALTER TABLE paiements MODIFY type ENUM('{$values}') NOT NULL");
    }
};
