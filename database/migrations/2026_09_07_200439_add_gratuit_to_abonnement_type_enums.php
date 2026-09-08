<?php

use App\Enums\AbonnementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite n'a pas de type ENUM (simple TEXT sans contrainte) : rien à faire.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $values = implode("','", AbonnementType::values());
        DB::statement("ALTER TABLE abonnements MODIFY type ENUM('{$values}') NOT NULL DEFAULT 'mensuel'");
        DB::statement("ALTER TABLE tarifs_abonnements MODIFY type ENUM('{$values}') NOT NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $values = implode("','", array_filter(AbonnementType::values(), fn($v) => $v !== AbonnementType::GRATUIT->value));
        DB::statement("ALTER TABLE abonnements MODIFY type ENUM('{$values}') NOT NULL DEFAULT 'mensuel'");
        DB::statement("ALTER TABLE tarifs_abonnements MODIFY type ENUM('{$values}') NOT NULL");
    }
};
