<?php

use App\Enums\MediaType;
use App\Enums\PropertyZone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer l'ancienne table si elle existe
        Schema::dropIfExists('images_properties');

        Schema::create('property_medias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_property');

            // Type de média
            $table->enum('type', MediaType::values())->default(MediaType::IMAGE->value);

            // Zone de la propriété concernée
            $table->enum('zone', PropertyZone::values())->default(PropertyZone::AUTRE->value);

            // Fichier
            $table->string('url');          // URL publique complète
            $table->string('chemin');       // chemin relatif dans storage/public
            $table->string('nom_original')->nullable(); // nom original du fichier
            $table->unsignedBigInteger('taille')->nullable(); // taille en octets
            $table->string('mime_type')->nullable();

            // Pour les vidéos : durée en secondes
            $table->integer('duree_secondes')->nullable();

            // Ordre et principale
            $table->boolean('is_principale')->default(false);
            $table->integer('ordre')->default(0);

            // Description optionnelle
            $table->string('description')->nullable();

            $table->foreign('id_property')->references('id')->on('properties')->onDelete('cascade');
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('restrict');

            // Index utiles
            $table->index(['id_property', 'type']);
            $table->index(['id_property', 'is_principale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_medias');
    }
};