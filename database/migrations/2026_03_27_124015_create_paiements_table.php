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
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->enum('type_paiement', ['pack_access', 'contact_access'])->default('pack_access');
            $table->decimal('montant', 10, 2);
            $table->string('reference_paiement')->unique();
            $table->string('methode_paiement');
            $table->enum('status', ['en_attente', 'effectue', 'échoué'])->default('en_attente');
            $table->string('numero_mobile')->nullable();

            $table->foreign('id_user')->references('id')->on('users')->onDelete('restrict');
            
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('restrict');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
