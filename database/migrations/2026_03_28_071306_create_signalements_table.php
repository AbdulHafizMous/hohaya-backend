<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\SignalementType;
use App\Enums\SignalementStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('signalements');

        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');                          // qui signale
            $table->unsignedBigInteger('id_property')->nullable();          // annonce signalée (si applicable)
            $table->unsignedBigInteger('id_user_signale')->nullable();      // utilisateur signalé
            $table->string('motif');
            $table->text('description');
            $table->enum('type_signalement', SignalementType::values())->default(SignalementType::AUTRE->value);
            $table->enum('status', SignalementStatus::values())->default(SignalementStatus::EN_ATTENTE->value);
            $table->text('note_admin')->nullable();                         // note de traitement
            $table->unsignedBigInteger('traite_par')->nullable();
            $table->timestamp('traite_le')->nullable();
            $table->foreign('id_user')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('id_property')->references('id')->on('properties')->onDelete('set null');
            $table->foreign('id_user_signale')->references('id')->on('users')->onDelete('set null');
            $table->foreign('traite_par')->references('id')->on('users')->onDelete('restrict');
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
        Schema::dropIfExists('signalements');
    }
};