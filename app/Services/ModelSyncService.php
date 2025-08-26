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
                if ($this->isEmbeddingModel($fullName)) {
                    $stats['skipped']++;

                    continue;
                }

                $shortName = explode(':', $fullName)[0];
                $size = (int) ($model['size'] ?? 0);

                $seen[$fullName] = true;

                $record = AIModel::query()->where('full_name', $fullName)->first();
                if (! $record) {
                    AIModel::query()->create([
                        'name' => $shortName,
                        'full_name' => $fullName,
                        'size' => $size,
                        'is_active' => true,
                        'last_synced_at' => $now,
                    ]);
                    $stats['created']++;
                } else {
                    $record->fill([
                        'name' => $shortName,
                        'size' => $size,
                        'is_active' => true,
                        'last_synced_at' => $now,
                    ]);
                    if ($record->isDirty()) {
                        $record->save();
                        $stats['updated']++;
                    } else {
                        // au moins mettre à jour last_synced_at
                        $record->touch();
                    }
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

            $record = AIModel::query()->where('full_name', $fullName)->first();
            if (! $record) {
                AIModel::query()->create([
                    'name' => $shortName,
                    'full_name' => $fullName,
                    'size' => $size,
                    'is_active' => true,
                    'last_synced_at' => $now,
                ]);

                return true;
            }
            $record->fill([
                'name' => $shortName,
                'size' => $size,
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
     * Détermine si un modèle est un modèle d'embedding.
     */
    protected function isEmbeddingModel(string $modelName): bool
    {
        $name = strtolower($modelName);
        $configured = strtolower((string) config('services.ollama.embedding_model', 'nomic-embed-text'));

        if ($name === $configured) {
            return true;
        }

        // Mots-clés typiques pour les embeddings
        $keywords = ['embed', 'embedding', 'encoder', 'e5', 'bge', 'gte', 'minilm', 'm3'];
        foreach ($keywords as $kw) {
            if (str_contains($name, $kw)) {
                return true;
            }
        }

        // Cas spécifique mentionné par l'utilisateur
        if (str_contains($name, 'bomic-embed')) {
            return true;
        }

        return false;
    }
}
