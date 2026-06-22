<?php

use App\Enums\PaiementStatus;
use App\Enums\PaiementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');

            $table->enum('type', PaiementType::values());
            $table->enum('status', PaiementStatus::values())->default(PaiementStatus::EN_ATTENTE->value);

            $table->decimal('montant', 10, 2);
            $table->string('devise')->default('XOF');

            // Références KKiaPay
            $table->string('kkiapay_transaction_id')->nullable()->unique();
            $table->string('kkiapay_reference')->nullable();
            $table->json('kkiapay_response')->nullable(); // réponse brute KKiaPay

            // Ce que le paiement concerne
            $table->unsignedBigInteger('id_abonnement')->nullable();
            $table->unsignedBigInteger('id_property')->nullable(); // pour déblocage contact

            // Infos Mobile Money
            $table->string('telephone_paiement')->nullable();
            $table->string('operateur')->nullable(); // MTN, Moov, etc.

            // En cas d'échec
            $table->text('raison_echec')->nullable();

            // Timestamp du paiement confirmé
            $table->timestamp('paye_le')->nullable();

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
            $table->index('kkiapay_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};