<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Création des rôles s'ils n'existent pas déjà
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $proprietaireRole = Role::firstOrCreate(['name' => 'proprietaire', 'guard_name' => 'web']);
        $chercheurRole = Role::firstOrCreate(['name' => 'chercheur', 'guard_name' => 'web']);

        // ADMIN - Utilisateur administrateur
        $admin = User::create([
            'name' => 'Admin Hohaya',
            'email' => 'admin@hohaya.com',
            'phone' => '+22997000001',
            'email_verified_at' => now(),
            'avatar' => '',
            'preferences' => json_encode(['language' => 'fr', 'notifications' => true]),
            'adress' => 'Cotonou, Bénin',
            'is_suscribed' => true,
            'subscription_start' => Carbon::now(),
            'is_verified' => true,
            'password' => Hash::make('hohaya@admin123'),
            'remember_token' => null,
        ]);
        $admin->assignRole($adminRole);

        // PROPRIETAIRES - Création de 10 propriétaires
        $proprietaires = [
            [
                'name' => 'Jean Dupont',
                'email' => 'jean.dupont@email.com',
                'phone' => '+22997000002',
                'adress' => 'Agoè, Cotonou',
                'avatar' => 'avatars/jean.jpg',
            ],
            [
                'name' => 'Marie Claire',
                'email' => 'marie.claire@email.com',
                'phone' => '+22997000003',
                'adress' => 'Tokoin, Cotonou',
                'avatar' => 'avatars/marie.jpg',
            ],
            [
                'name' => 'Koffi Mensah',
                'email' => 'koffi.mensah@email.com',
                'phone' => '+22997000004',
                'adress' => 'Adidogomé, Cotonou',
                'avatar' => 'avatars/koffi.jpg',
            ],
            [
                'name' => 'Ama Adjovi',
                'email' => 'ama.adjovi@email.com',
                'phone' => '+22997000005',
                'adress' => 'Bè, Cotonou',
                'avatar' => 'avatars/ama.jpg',
            ],
            [
                'name' => 'Pierre Akakpo',
                'email' => 'pierre.akakpo@email.com',
                'phone' => '+22997000006',
                'adress' => 'Kodjoviakopé, Cotonou',
                'avatar' => 'avatars/pierre.jpg',
            ],
            [
                'name' => 'Sophie Amegnran',
                'email' => 'sophie.amegnran@email.com',
                'phone' => '+22997000007',
                'adress' => 'Hédzranawoé, Cotonou',
                'avatar' => 'avatars/sophie.jpg',
            ],
            [
                'name' => 'David Lawson',
                'email' => 'david.lawson@email.com',
                'phone' => '+22997000008',
                'adress' => 'Dogan, Cotonou',
                'avatar' => 'avatars/david.jpg',
            ],
            [
                'name' => 'Grace Adjei',
                'email' => 'grace.adjei@email.com',
                'phone' => '+22997000009',
                'adress' => 'Nyékonakpoé, Cotonou',
                'avatar' => 'avatars/grace.jpg',
            ],
            [
                'name' => 'Michel Tete',
                'email' => 'michel.tete@email.com',
                'phone' => '+22997000010',
                'adress' => 'Amoutivé, Cotonou',
                'avatar' => 'avatars/michel.jpg',
            ],
            [
                'name' => 'Evelyne Dossou',
                'email' => 'evelyne.dossou@email.com',
                'phone' => '+22997000011',
                'adress' => 'Attiegou, Cotonou',
                'avatar' => 'avatars/evelyne.jpg',
            ],
        ];

        foreach ($proprietaires as $index => $proprietaireData) {
            $subscriptionEnd = Carbon::now()->addMonths(rand(1, 12));

            $proprietaire = User::create([
                'name' => $proprietaireData['name'],
                'email' => $proprietaireData['email'],
                'phone' => $proprietaireData['phone'],
                'email_verified_at' => now(),
                'avatar' => '',
                'preferences' => json_encode(['language' => 'fr', 'notifications' => true]),
                'adress' => $proprietaireData['adress'],
                'is_suscribed' => true,
                'subscription_start' => Carbon::now(),
                'is_verified' => rand(0, 1) ? true : false,
                'password' => Hash::make('password123'),
                'remember_token' => null,
            ]);
            $proprietaire->assignRole($proprietaireRole);
        }

        // CHERCHEURS - Création de 20 chercheurs (sans doublons)
        $usedEmails = [];
        $firstNames = ['Kodjo', 'Afi', 'Mensah', 'Esi', 'Kwame', 'Abena', 'Yao', 'Akua', 'Kossi', 'Adzo', 
                       'Tete', 'Beatrice', 'Clement', 'Dorothee', 'Emmanuel', 'Fanny', 'Gaston', 'Henriette', 
                       'Ignace', 'Juliette'];
        $lastNames = ['Amegah', 'Kouassi', 'Togbe', 'Adjah', 'Gbedo', 'Kpodar', 'Agbo', 'Kokou', 'Ami', 'Sossou'];
        $quartiers = ['Agoè', 'Tokoin', 'Bè', 'Adidogomé', 'Kodjoviakopé', 'Dogan', 'Nyékonakpoé', 'Amoutivé', 'Attiegou', 'Hédzranawoé'];

        for ($i = 0; $i < 20; $i++) {
            do {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $email = strtolower($firstName . '.' . $lastName . '@email.com');
                $email = str_replace(['é', 'è', 'ê', 'ë'], 'e', $email);
                $email = str_replace(['à', 'â', 'ä'], 'a', $email);
                $email = str_replace(['î', 'ï'], 'i', $email);
                $email = str_replace(['ô', 'ö'], 'o', $email);
                $email = str_replace(['û', 'ü'], 'u', $email);
                $email = str_replace(['ç'], 'c', $email);
            } while (in_array($email, $usedEmails) || User::where('email', $email)->exists());
            
            $usedEmails[] = $email;
            
            $name = $firstName . ' ' . $lastName;
            $phone = '+22965' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $quartier = $quartiers[array_rand($quartiers)];

            $randomQuartiers = (array) array_rand(array_flip($quartiers), rand(1, 3));
            
            $preferences = json_encode([
                'budget_min' => rand(50000, 100000),
                'budget_max' => rand(150000, 300000),
                'preferred_quartiers' => $randomQuartiers,
                'property_type' => rand(0, 1) ? 'appartement' : 'studio',
                'bedrooms' => rand(1, 3),
            ]);

            $chercheur = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => $phone,
                    'email_verified_at' => rand(0, 1) ? now() : null,
                    'avatar' => '',
                    'preferences' => $preferences,
                    'adress' => $quartier . ', Cotonou',
                    'is_suscribed' => false,
                    'subscription_start' => null,
                    'subscription_end' => null,
                    'is_verified' => rand(0, 1) ? true : false, 
                    'password' => Hash::make('password123'),
                    'remember_token' => null,
                ]
            );
            $chercheur->syncRoles([$chercheurRole]);
        }

        $this->command->info('Utilisateurs créés avec succès :');
        $this->command->info('- 1 Admin');
        $this->command->info('- 10 Propriétaires');
        $this->command->info('- 20 Chercheurs');

    }
}
