<?php

use App\Enums\AbonnementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifs_abonnements', function (Blueprint $table) {
            $table->id();
            $table->enum('type', AbonnementType::values())->unique();
            $table->decimal('montant', 10, 2);
            $table->string('devise')->default('XOF');
            $table->boolean('is_actif')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifs_abonnements');
    }
};