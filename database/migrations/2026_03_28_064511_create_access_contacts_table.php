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
        Schema::create('access_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->unsignedBigInteger('id_property')->nullable();
            $table->unsignedBigInteger('id_paiement')->nullable();

            $table->foreign('id_user')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('id_property')->references('id')->on('properties')->onDelete('restrict');
            $table->foreign('id_paiement')->references('id')->on('paiements')->onDelete('restrict');

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
        Schema::dropIfExists('access_contacts');
    }
};
