<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->decimal('part_hohaya', 10, 2)->nullable()->after('montant');
            $table->decimal('part_proprietaire', 10, 2)->nullable()->after('part_hohaya');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn(['part_hohaya', 'part_proprietaire']);
        });
    }
};
