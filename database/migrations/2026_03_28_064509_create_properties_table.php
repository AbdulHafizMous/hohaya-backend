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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->string('title');
            $table->text('description');
            $table->string('quartier');
            $table->string('ville');
            $table->decimal('prix_loyer', 10, 2);
            $table->enum('type_logement', ['appartement', 'maison', 'studio'])->default('maison');
            $table->string('condition');
            $table->integer('nb_avance')->default(3);
            $table->decimal('caution_electricite', 10, 2)->nullable();
            $table->decimal('caution_eau', 10, 2)->nullable();
            $table->integer('nb_pieces');
            $table->enum('status', ['disponible', 'loué'])->default('disponible');
            $table->dateTime('date_debut_louer')->nullable();
            $table->boolean('is_verified')->default(false);

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
        Schema::dropIfExists('properties');
    }
};
