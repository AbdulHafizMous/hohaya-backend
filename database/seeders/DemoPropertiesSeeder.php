<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoPropertiesSeeder extends Seeder
{
    /**
     * Photos Unsplash (intérieurs de maisons/chambres) — vérifiées accessibles.
     */
    private array $photoIds = [
        '1512918728675-ed5a9ecdebfd',
        '1502672260266-1c1ef2d93688',
        '1493809842364-78817add7ffb',
        '1556911220-e15b29be8c8f',
        '1484154218962-a197022b5858',
        '1560448204-e02f11c3d0e2',
        '1522708323590-d24dbb6b0267',
        '1513694203232-719a280e022f',
        '1560185127-6ed189bf02f4',
        '1583847268964-b28dc8f51f92',
        '1615873968403-89e068629265',
        '1598928506311-c55ded91a20c',
        '1598300042247-d088f8ab3a91',
        '1560184897-ae75f418493e',
        '1571508601891-ca5e7a713859',
        '1523217582562-09d0def993a6',
        '1543373014-cfe4f4bc1cdf',
        '1600585154340-be6161a56a0c',
        '1600607687939-ce8a6c25118c',
        '1600210492486-724fe5c67fb0',
        '1600566753086-00f18fb6b3ea',
        '1600121848594-d8644e57abab',
        '1600585152220-90363fe7e115',
        '1600489000022-c2086d79f9d4',
    ];

    private array $villes = [
        ['ville' => 'Cotonou', 'communes' => ['Cadjehoun', 'Akpakpa', 'Fidjrossè', 'Gbégamey', 'Sainte Rita']],
        ['ville' => 'Abomey-Calavi', 'communes' => ['Godomey', 'Womey', 'Zogbo', 'Togba']],
        ['ville' => 'Porto-Novo', 'communes' => ['Ouando', 'Djassin', 'Attakè']],
        ['ville' => 'Parakou', 'communes' => ['Titirou', 'Banikanni', 'Zongo']],
        ['ville' => 'Ouidah', 'communes' => ['Centre-ville', 'Avlékété']],
        ['ville' => 'Bohicon', 'communes' => ['Agongointo', 'Avogbanna']],
    ];

    private array $types = ['maison', 'appartement', 'studio', 'chambre', 'villa', 'duplex'];

    public function run(): void
    {
        if (Property::count() >= 24) {
            $this->command?->info('Des annonces existent déjà, seed ignoré.');
            return;
        }

        $owners = User::role('owner')->pluck('id')->all();
        if (empty($owners)) {
            $this->command?->warn('Aucun propriétaire trouvé — lancez UserSeeder avant DemoPropertiesSeeder.');
            return;
        }

        foreach ($this->photoIds as $i => $photoId) {
            $ville = $this->villes[$i % count($this->villes)];
            $commune = $ville['communes'][array_rand($ville['communes'])];
            $type = $this->types[$i % count($this->types)];
            $nbPieces = rand(1, 5);
            $prix = rand(15, 90) * 1000;
            $ownerId = $owners[$i % count($owners)];

            $property = Property::create([
                'id_user'             => $ownerId,
                'title'               => ucfirst($type) . " {$nbPieces} pièce" . ($nbPieces > 1 ? 's' : '') . " à {$commune}",
                'description'         => "Belle {$type} située à {$commune}, {$ville['ville']}. Proche des commerces et des écoles, idéale pour étudiant. Quartier calme et sécurisé.",
                'indications_acces'   => "Après le carrefour principal de {$commune}, suivre les indications jusqu'au portail bleu.",
                'quartier'            => $commune,
                'commune'             => $commune,
                'ville'               => $ville['ville'],
                'pays'                => 'Bénin',
                'prix_loyer'          => $prix,
                'devise'              => 'XOF',
                'type_logement'       => $type,
                'condition'           => ['Bon état', 'Très bon état', 'Neuf', 'À rafraîchir'][array_rand([0, 1, 2, 3])],
                'nb_avance'           => rand(1, 3),
                'caution_electricite' => rand(5, 20) * 1000,
                'caution_eau'         => rand(3, 15) * 1000,
                'nb_pieces'           => $nbPieces,
                'status'              => 'disponible',
                'is_verified'         => true,
                'eau_courante'        => (bool) rand(0, 1),
                'electricite'         => true,
                'gardien'             => (bool) rand(0, 1),
                'parking'             => (bool) rand(0, 1),
                'meuble'              => (bool) rand(0, 1),
            ]);

            $this->attachPhoto($property, $photoId, $i);
        }

        $this->command?->info('24 annonces de démonstration créées avec photos.');
    }

    private function attachPhoto(Property $property, string $photoId, int $index): void
    {
        try {
            $response = Http::timeout(15)->get("https://images.unsplash.com/photo-{$photoId}", [
                'w' => 900,
                'q' => 80,
                'fit' => 'crop',
            ]);

            if (!$response->successful()) {
                return;
            }

            $filename = Str::random(32) . '.jpg';
            $path = "properties/{$property->id}/images/{$filename}";
            Storage::disk('public')->put($path, $response->body());

            PropertyMedia::create([
                'id_property'   => $property->id,
                'type'          => 'image',
                'zone'          => 'salon',
                'url'           => asset('storage/' . $path),
                'chemin'        => $path,
                'nom_original'  => $filename,
                'taille'        => strlen($response->body()),
                'mime_type'     => 'image/jpeg',
                'is_principale' => true,
                'ordre'         => 0,
            ]);
        } catch (\Throwable $e) {
            $this->command?->warn("Photo {$index} non téléchargée : " . $e->getMessage());
        }
    }
}
