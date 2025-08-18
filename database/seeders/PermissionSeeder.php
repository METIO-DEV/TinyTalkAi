<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        // Réinitialiser les rôles et permissions mis en cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Créer les permissions
        $permissions = [
            // Permissions utilisateurs
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Permissions groupes
            'view groups',
            'create groups',
            'edit groups',
            'delete groups',

            // Permissions rôles
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',

            // Permissions modèles Ollama
            'view models',
            'manage models',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Créer les rôles et assigner les permissions
        $role = Role::create(['name' => 'super-admin']);
        $role->givePermissionTo(Permission::all());

        $role = Role::create(['name' => 'admin']);
        $role->givePermissionTo([
            'view users', 'create users', 'edit users',
            'view groups', 'create groups', 'edit groups',
            'view roles',
            'view models', 'manage models',
        ]);

        $role = Role::create(['name' => 'user']);
        $role->givePermissionTo([]);
    }
}
