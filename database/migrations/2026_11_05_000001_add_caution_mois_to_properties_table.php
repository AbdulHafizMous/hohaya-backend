<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Caution unique exprimée en nombre de mois (remplace caution_electricite + caution_eau)
            $table->integer('caution_mois')->nullable()->default(0)->after('nb_avance');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('caution_mois');
        });
    }
};
