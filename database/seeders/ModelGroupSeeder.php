<?php

namespace Database\Seeders;

use App\Models\AIModel;
use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ModelGroupSeeder extends Seeder
{
    /**
     * Assigne tous les modèles au groupe par défaut
     */
    public function run(): void
    {
        // Récupérer le groupe par défaut (généralement créé par PermissionSeeder)
        $defaultGroup = Group::where('name', 'default')->first();

        if (! $defaultGroup) {
            // Créer le groupe par défaut s'il n'existe pas
            $defaultGroup = Group::create([
                'name' => 'default',
                'description' => 'Groupe par défaut avec accès à tous les modèles',
            ]);
            Log::info('Groupe par défaut créé', ['id' => $defaultGroup->id]);
        }

        // Récupérer tous les modèles actifs
        $models = AIModel::where('is_active', true)->get();

        // Assigner chaque modèle au groupe par défaut
        foreach ($models as $model) {
            // Vérifier si l'association existe déjà pour éviter les doublons
            if (! $model->groups()->where('groups.id', $defaultGroup->id)->exists()) {
                $model->groups()->attach($defaultGroup->id);
                Log::info('Modèle assigné au groupe par défaut', [
                    'model_id' => $model->id,
                    'model_name' => $model->full_name,
                    'group_id' => $defaultGroup->id,
                ]);
            }
        }

        Log::info('Assignation des modèles au groupe par défaut terminée', [
            'models_count' => $models->count(),
            'default_group_id' => $defaultGroup->id,
        ]);
    }
}
