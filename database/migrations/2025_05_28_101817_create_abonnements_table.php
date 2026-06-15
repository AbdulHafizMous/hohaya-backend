<?php

use App\Enums\AbonnementStatus;
use App\Enums\AbonnementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');

            $table->enum('type', AbonnementType::values())->default(AbonnementType::MENSUEL->value);
            $table->enum('status', AbonnementStatus::values())->default(AbonnementStatus::EN_ATTENTE->value);

            $table->decimal('montant', 10, 2);
            $table->string('devise')->default('XOF');

            $table->timestamp('date_debut')->nullable();
            $table->timestamp('date_fin')->nullable();

            // Renouvellement automatique
            $table->boolean('auto_renew')->default(false);

            // Référence au paiement associé
            $table->unsignedBigInteger('id_paiement')->nullable();

            $table->text('note')->nullable();

            $table->foreign('id_user')->references('id')->on('users')->onDelete('restrict');
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['id_user', 'status']);
            $table->index('date_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements');
    }
};