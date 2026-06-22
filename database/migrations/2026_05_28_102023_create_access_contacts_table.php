<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');      // chercheur
            $table->unsignedBigInteger('id_property');
            $table->unsignedBigInteger('id_paiement');  // paiement associé

            $table->timestamp('expire_le')->nullable();  // accès limité dans le temps

            $table->foreign('id_user')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('id_property')->references('id')->on('properties')->onDelete('cascade');
            $table->foreign('id_paiement')->references('id')->on('paiements')->onDelete('restrict');
            $table->timestamps();

            // Un user ne peut débloquer qu'une fois un contact
            $table->unique(['id_user', 'id_property']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_contacts');
    }
};
 