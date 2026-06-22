<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\PropertyType;
use App\Enums\PropertyStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Modifier les enums pour utiliser les nouvelles valeurs
            $table->enum('type_logement', PropertyType::values())
                ->default(PropertyType::MAISON->value)
                ->change();
            $table->enum('status', PropertyStatus::values())
                ->default(PropertyStatus::DISPONIBLE->value)
                ->change();
            // Champs manquants contexte africain
            $table->string('pays')->default('Bénin')->after('ville');
            $table->string('commune')->nullable()->after('pays');
            $table->text('indications_acces')->nullable()->after('description'); // pas toujours d'adresse précise
            $table->boolean('eau_courante')->default(true)->after('nb_pieces');
            $table->boolean('electricite')->default(true)->after('eau_courante');
            $table->boolean('gardien')->default(false)->after('electricite');
            $table->boolean('parking')->default(false)->after('gardien');
            $table->boolean('meuble')->default(false)->after('parking');
            $table->string('devise')->default('XOF')->after('prix_loyer'); // Franc CFA
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'pays', 'commune', 'indications_acces',
                'eau_courante', 'electricite', 'gardien',
                'parking', 'meuble', 'devise',
            ]);
        });
    }
};