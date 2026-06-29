<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favoris', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_property');
            $table->foreign('id_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('id_property')->references('id')->on('properties')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['id_user', 'id_property']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favoris');
    }
};
