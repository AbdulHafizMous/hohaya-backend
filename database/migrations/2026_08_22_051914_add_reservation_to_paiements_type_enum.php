<?php

use App\Enums\PaiementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $values = implode("','", PaiementType::values());
        DB::statement("ALTER TABLE paiements MODIFY type ENUM('{$values}') NOT NULL");
    }

    public function down(): void
    {
        $values = implode("','", array_filter(PaiementType::values(), fn($v) => $v !== PaiementType::RESERVATION->value));
        DB::statement("ALTER TABLE paiements MODIFY type ENUM('{$values}') NOT NULL");
    }
};
