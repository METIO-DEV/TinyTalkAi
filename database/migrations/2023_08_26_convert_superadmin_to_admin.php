<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the roles table exists before proceeding
        if (! Schema::hasTable('roles')) {
            return;
        }

        // Récupérer les IDs des rôles
        $superAdminRoleId = DB::table('roles')->where('name', 'super-admin')->value('id');
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        if ($superAdminRoleId && $adminRoleId) {
            // Récupérer tous les utilisateurs avec le rôle super-admin
            $superAdminUsers = DB::table('model_has_roles')
                ->where('role_id', $superAdminRoleId)
                ->get();

            // Assigner le rôle admin à ces utilisateurs
            foreach ($superAdminUsers as $user) {
                // Vérifier si l'utilisateur a déjà le rôle admin
                $hasAdminRole = DB::table('model_has_roles')
                    ->where('role_id', $adminRoleId)
                    ->where('model_id', $user->model_id)
                    ->where('model_type', $user->model_type)
                    ->exists();

                // Si l'utilisateur n'a pas déjà le rôle admin, l'ajouter
                if (! $hasAdminRole) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $adminRoleId,
                        'model_type' => $user->model_type,
                        'model_id' => $user->model_id,
                    ]);
                }

                // Supprimer le rôle super-admin
                DB::table('model_has_roles')
                    ->where('role_id', $superAdminRoleId)
                    ->where('model_id', $user->model_id)
                    ->where('model_type', $user->model_type)
                    ->delete();
            }

            // Supprimer le rôle super-admin
            DB::table('roles')->where('id', $superAdminRoleId)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the roles table exists before proceeding
        if (! Schema::hasTable('roles')) {
            return;
        }

        // Recréer le rôle super-admin s'il n'existe pas
        if (! DB::table('roles')->where('name', 'super-admin')->exists()) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'super-admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Donner toutes les permissions au rôle super-admin
            $permissions = DB::table('permissions')->pluck('id');
            foreach ($permissions as $permissionId) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        // Note: Nous ne réattribuons pas le rôle super-admin aux utilisateurs
        // car nous ne pouvons pas savoir qui avait ce rôle auparavant
    }
};
