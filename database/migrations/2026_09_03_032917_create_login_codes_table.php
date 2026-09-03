<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user')->constrained('users')->cascadeOnDelete();
            $table->string('code'); // haché (Hash::make)
            $table->string('type'); // login_seeker | 2fa
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['id_user', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_codes');
    }
};
