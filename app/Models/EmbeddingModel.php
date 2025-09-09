<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmbeddingModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Obtenir le modèle d'embedding actif
     */
    public static function getActiveModel(): string
    {
        $activeModel = self::where('is_active', true)->first();
        
        return $activeModel ? $activeModel->model_name : 'nomic-embed-text';
    }

    /**
     * Définir un modèle comme actif
     */
    public static function setActiveModel(string $modelName): void
    {
        // Désactiver tous les modèles
        self::query()->update(['is_active' => false]);
        
        // Activer le modèle spécifié ou le créer s'il n'existe pas
        self::updateOrCreate(
            ['model_name' => $modelName],
            ['is_active' => true]
        );
    }

    /**
     * Obtenir tous les modèles d'embedding disponibles depuis AIModel
     */
    public static function getAvailableModels()
    {
        return AIModel::where('family', 'embedding')
            ->where('is_active', true)
            ->get();
    }
}
