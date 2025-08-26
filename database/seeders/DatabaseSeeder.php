<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Exécuter le seeder de permissions en premier
        $this->call(PermissionSeeder::class);

        // Exécuter le seeder pour assigner les modèles aux groupes
        $this->call(ModelGroupSeeder::class);

        // Créer un utilisateur admin
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@tinytalk.ai',
            'password' => Hash::make('password'),
        ]);

        // Assigner le rôle admin
        $admin->assignRole('admin');

        // Créer un second admin (ancien super-admin)
        $secondAdmin = User::factory()->create([
            'name' => 'Gaston Admin',
            'email' => 'gaston@metio.fr',
            'password' => Hash::make('12345678'),
        ]);

        // Assigner le rôle admin
        $secondAdmin->assignRole('admin');

        // Créer un utilisateur de test
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ])->assignRole('user');
    }
}
