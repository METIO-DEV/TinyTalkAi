<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
        $admin = User::query()->updateOrCreate(['email' => 'admin@tinytalk.ai'], [
            'name' => 'Admin',
            'password' => 'password',
        ]);

        // Assigner le rôle admin
        $admin->assignRole('admin');

        // Créer un second admin (ancien super-admin)
        $secondAdmin = User::query()->updateOrCreate(['email' => 'gaston@metio.fr'], [
            'name' => 'Gaston Admin',
            'password' => '12345678',
        ]);

        // Assigner le rôle admin
        $secondAdmin->assignRole('admin');

        // Créer un utilisateur de test
        User::query()->updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => 'password',
        ])->assignRole('user');
    }
}
