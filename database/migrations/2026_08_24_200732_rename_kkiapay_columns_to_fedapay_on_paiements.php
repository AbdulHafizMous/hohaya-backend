<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->renameColumn('kkiapay_transaction_id', 'fedapay_transaction_id');
            $table->renameColumn('kkiapay_reference', 'fedapay_reference');
            $table->renameColumn('kkiapay_response', 'fedapay_response');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->renameColumn('fedapay_transaction_id', 'kkiapay_transaction_id');
            $table->renameColumn('fedapay_reference', 'kkiapay_reference');
            $table->renameColumn('fedapay_response', 'kkiapay_response');
        });
    }
};
