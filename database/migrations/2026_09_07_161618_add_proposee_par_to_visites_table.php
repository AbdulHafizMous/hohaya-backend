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
        Schema::table('visites', function (Blueprint $table) {
            // Qui a initié la proposition de date — l'autre partie doit alors confirmer/refuser.
            $table->enum('proposee_par', ['chercheur', 'proprietaire'])->default('chercheur')->after('id_property');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visites', function (Blueprint $table) {
            $table->dropColumn('proposee_par');
        });
    }
};
