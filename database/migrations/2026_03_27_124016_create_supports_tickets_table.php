<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\TicketStatus;
use App\Enums\TicketCategorie;

return new class extends Migration
{
    public function up(): void
    {
        // Recréer la table avec les bons champs
        Schema::dropIfExists('supports_tickets');

        Schema::create('supports_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->string('sujet');
            $table->text('message');
            $table->enum('status', TicketStatus::values())->default(TicketStatus::OUVERT->value);
            $table->enum('categorie', TicketCategorie::values())->default(TicketCategorie::GENERAL->value);
            // Réponse admin — champ manquant dans la version originale
            $table->text('reponse_admin')->nullable();
            $table->unsignedBigInteger('repondu_par')->nullable(); // id de l'admin
            $table->timestamp('repondu_le')->nullable();
            // Contexte africain : canal de contact préféré
            $table->enum('canal_preference', ['email', 'whatsapp', 'appel'])->default('email');
            $table->string('telephone_contact')->nullable();
            $table->foreign('id_user')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('repondu_par')->references('id')->on('users')->onDelete('restrict');
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

    public function down(): void
    {
        Schema::dropIfExists('supports_tickets');
    }
};