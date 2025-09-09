<?php

namespace App\Services;

use App\Models\AIModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ModelSyncService
{
    protected string $ollamaHost;

    protected string $ollamaPort;

    public function __construct()
    {
        $this->ollamaHost = config('services.ollama.host', 'localhost');
        $this->ollamaPort = config('services.ollama.port', '11434');
    }

    /**
     * Synchronise les modèles de génération depuis Ollama dans la base de données.
     *
     * @param  bool  $deactivateMissing  Si true, désactive (is_active=false) les modèles absents du serveur Ollama
     * @return array{created:int,updated:int,deactivated:int,skipped:int,errors:int}
     */
    public function sync(bool $deactivateMissing = false): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'deactivated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        try {
            $url = "http://{$this->ollamaHost}:{$this->ollamaPort}/api/tags";
            $response = Http::timeout(15)->get($url);

            if (! $response->successful()) {
                Log::error('ModelSyncService: échec de récupération des modèles Ollama', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $stats['errors']++;

                return $stats;
            }

            $payload = $response->json();
            $remoteModels = $payload['models'] ?? [];

            $now = now();
            $seen = [];

            foreach ($remoteModels as $model) {
                $fullName = $model['name'] ?? null;
                if (empty($fullName)) {
                    $stats['skipped']++;

                    continue;
                }

                // Filtrage: ignorer les modèles d'embedding
                // if ($this->isEmbeddingModel($fullName)) {
                //     $stats['skipped']++;

                //     continue;
                // }

                $shortName = explode(':', $fullName)[0];
                $size = (int) ($model['size'] ?? 0);

                $seen[$fullName] = true;

                // Déterminer la famille du modèle
                $family = $this->isEmbeddingModel($fullName) ? 'embedding' : 'llm';

                $record = AIModel::query()->where('full_name', $fullName)->first();
                if (! $record) {
                    AIModel::query()->create([
                        'name' => $shortName,
                        'full_name' => $fullName,
                        'size' => $size,
                        'family' => $family,
                        'is_active' => true,
                        'last_synced_at' => $now,
                    ]);
                    $stats['created']++;
                } else {
                    $record->fill([
                        'name' => $shortName,
                        'size' => $size,
                        'family' => $family,
                        'is_active' => true,
                        'last_synced_at' => $now,
                    ]);
                    $record->save(); // Force la mise à jour
                    $stats['updated']++;
                }
            }

            if ($deactivateMissing) {
                // Désactiver les modèles locaux non vus sur le serveur
                $toDeactivate = AIModel::query()
                    ->whereNotIn('full_name', array_keys($seen))
                    ->where('is_active', true)
                    ->get();

                foreach ($toDeactivate as $m) {
                    $m->is_active = false;
                    $m->last_synced_at = $now;
                    $m->save();
                    $stats['deactivated']++;
                }
            }

            return $stats;
        } catch (\Throwable $e) {
            Log::error('ModelSyncService: exception pendant la synchronisation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $stats['errors']++;

            return $stats;
        }
    }

    /**
     * Upsert un seul modèle identifié par son nom complet depuis Ollama.
     * Retourne true si créé/mis à jour, false si ignoré (embedding ou introuvable).
     */
    public function syncOne(string $fullName): bool
    {
        try {
            $url = "http://{$this->ollamaHost}:{$this->ollamaPort}/api/tags";
            $response = Http::timeout(15)->get($url);
            if (! $response->successful()) {
                Log::error('ModelSyncService::syncOne: échec /api/tags', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }
            $list = $response->json('models', []);
            $target = collect($list)->firstWhere('name', $fullName);
            if (! $target) {
                Log::warning('ModelSyncService::syncOne: modèle non trouvé dans /api/tags', ['fullName' => $fullName]);

                return false;
            }
            if ($this->isEmbeddingModel($fullName)) {
                Log::info('ModelSyncService::syncOne: modèle ignoré (embedding)', ['fullName' => $fullName]);

                return false;
            }
            $shortName = explode(':', $fullName)[0];
            $size = (int) ($target['size'] ?? 0);
            $now = now();

            // Déterminer la famille du modèle
            $family = $this->isEmbeddingModel($fullName) ? 'embedding' : 'llm';

            $record = AIModel::query()->where('full_name', $fullName)->first();
            if (! $record) {
                AIModel::query()->create([
                    'name' => $shortName,
                    'full_name' => $fullName,
                    'size' => $size,
                    'family' => $family,
                    'is_active' => true,
                    'last_synced_at' => $now,
                ]);

                return true;
            }
            $record->fill([
                'name' => $shortName,
                'size' => $size,
                'family' => $family,
                'is_active' => true,
                'last_synced_at' => $now,
            ]);
            if ($record->isDirty()) {
                $record->save();
            } else {
                $record->touch();
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('ModelSyncService::syncOne exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Récupère les détails d'un modèle via l'API /api/show d'Ollama.
     */
    protected function getModelDetails(string $modelName): ?array
    {
        try {
            $url = "http://{$this->ollamaHost}:{$this->ollamaPort}/api/show";
            $response = Http::timeout(30)->post($url, ['name' => $modelName]);

            if (! $response->successful()) {
                Log::warning('ModelSyncService: échec /api/show', [
                    'model' => $modelName,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('ModelSyncService: exception /api/show', [
                'model' => $modelName,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Détermine si un modèle est un modèle d'embedding basé sur ses capabilities.
     */
    protected function isEmbeddingModel(string $modelName): bool
    {
        $details = $this->getModelDetails($modelName);

        if (! $details) {
            Log::warning('ModelSyncService: impossible de récupérer les détails du modèle', [
                'model' => $modelName,
            ]);

            return false;
        }

        // Vérifier les capacités
        if (isset($details['capabilities'])) {
            $capabilities = $details['capabilities'];

            // Les modèles d'embedding n'ont pas les capacités "completion" ou "chat"
            $hasCompletion = in_array('completion', $capabilities);
            $hasChat = in_array('chat', $capabilities);

            $isEmbedding = ! $hasCompletion && ! $hasChat;

            Log::info('ModelSyncService: analyse des capabilities', [
                'model' => $modelName,
                'capabilities' => $capabilities,
                'has_completion' => $hasCompletion,
                'has_chat' => $hasChat,
                'is_embedding' => $isEmbedding,
            ]);

            return $isEmbedding;
        }

        Log::warning('ModelSyncService: aucune capability trouvée pour le modèle', [
            'model' => $modelName,
        ]);

        return false;
    }
}
