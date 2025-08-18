<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QdrantCollectionsService
{
    /**
     * Configuration de l'API Qdrant
     */
    protected string $qdrantHost;

    protected string $qdrantPort;

    protected string $qdrantCollection;

    /**
     * Modèle d'embedding utilisé (pour déterminer la dimension des vecteurs)
     */
    protected string $embeddingModel;

    /**
     * Constructeur du service
     */
    public function __construct()
    {
        // Configuration Qdrant
        $this->qdrantHost = config('services.qdrant.host', 'host.docker.internal');
        $this->qdrantPort = config('services.qdrant.port', '6333');
        $this->qdrantCollection = config('services.qdrant.collection', 'docs');

        // Modèle d'embedding par défaut
        $this->embeddingModel = config('services.ollama.embedding_model', 'nomic-embed-text');
    }

    /**
     * Définit le modèle d'embedding à utiliser
     *
     * @param  string  $modelName  Nom du modèle d'embedding
     */
    public function setEmbeddingModel(string $modelName): void
    {
        $this->embeddingModel = $modelName;
        Log::info('Modèle d\'embedding défini', ['model' => $modelName]);
    }

    /**
     * Définit le nom de la collection Qdrant à utiliser
     *
     * @param  string  $collectionName  Nom de la collection
     */
    public function setQdrantCollection(string $collectionName): void
    {
        $this->qdrantCollection = $collectionName;
        Log::info('Collection Qdrant définie', ['collection' => $collectionName]);
    }

    /**
     * Récupère le nom de la collection Qdrant courante
     *
     * @return string Nom de la collection
     */
    public function getQdrantCollection(): string
    {
        return $this->qdrantCollection;
    }

    /**
     * Liste toutes les collections disponibles dans Qdrant
     *
     * @return array Liste des collections
     */
    public function listCollections(): array
    {
        try {
            $qdrantUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections";

            Log::info('Tentative de listage des collections dans Qdrant', [
                'url' => $qdrantUrl,
            ]);

            $response = Http::get($qdrantUrl);

            if ($response->successful()) {
                $collections = $response->json('result.collections', []);

                Log::info('Collections récupérées avec succès', [
                    'count' => count($collections),
                ]);

                // Extraire uniquement les noms des collections et exclure la collection "docs"
                $collectionNames = array_column($collections, 'name');

                // Filtrer les noms vides et la collection "docs"
                return array_filter($collectionNames, function ($name) {
                    return ! empty($name) && $name !== 'docs';
                });
            } else {
                Log::error('Erreur lors du listage des collections', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception lors du listage des collections', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Crée une nouvelle collection dans Qdrant
     *
     * @param  string  $collectionName  Nom de la collection à créer
     * @return bool True si la création a réussi, false sinon
     */
    public function createCollection(string $collectionName): bool
    {
        try {
            $qdrantUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections/{$collectionName}";

            Log::info('Tentative de création d\'une collection dans Qdrant', [
                'url' => $qdrantUrl,
                'collectionName' => $collectionName,
            ]);

            // Configuration de la collection pour les embeddings
            // La dimension dépend du modèle d'embedding utilisé
            $dimension = $this->getEmbeddingDimension();

            $response = Http::put($qdrantUrl, [
                'vectors' => [
                    'size' => $dimension,
                    'distance' => 'Cosine',
                ],
            ]);

            if ($response->successful()) {
                Log::info('Collection créée avec succès', [
                    'collectionName' => $collectionName,
                    'response' => $response->json(),
                ]);

                // Mettre à jour la collection courante
                $this->qdrantCollection = $collectionName;

                return true;
            } else {
                Log::error('Erreur lors de la création de la collection', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception lors de la création de la collection', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Supprime une collection dans Qdrant
     *
     * @param  string  $collectionName  Nom de la collection à supprimer
     * @return bool True si la suppression a réussi, false sinon
     */
    public function deleteCollection(string $collectionName): bool
    {
        try {
            $qdrantUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections/{$collectionName}";

            Log::info('Tentative de suppression d\'une collection dans Qdrant', [
                'url' => $qdrantUrl,
                'collectionName' => $collectionName,
            ]);

            $response = Http::delete($qdrantUrl);

            if ($response->successful()) {
                Log::info('Collection supprimée avec succès', [
                    'collectionName' => $collectionName,
                    'response' => $response->json(),
                ]);

                // Si la collection supprimée était la collection courante, réinitialiser
                if ($this->qdrantCollection === $collectionName) {
                    $this->qdrantCollection = config('services.qdrant.collection', 'docs');
                }

                return true;
            } else {
                Log::error('Erreur lors de la suppression de la collection', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception lors de la suppression de la collection', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Vérifie si une collection existe dans Qdrant
     *
     * @param  string  $collectionName  Nom de la collection à vérifier
     * @return bool True si la collection existe, false sinon
     */
    public function collectionExists(string $collectionName): bool
    {
        try {
            $qdrantUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections/{$collectionName}";

            Log::info('Vérification de l\'existence d\'une collection dans Qdrant', [
                'url' => $qdrantUrl,
                'collectionName' => $collectionName,
            ]);

            $response = Http::get($qdrantUrl);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception lors de la vérification de l\'existence de la collection', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Récupère les informations sur une collection dans Qdrant
     *
     * @param  string  $collectionName  Nom de la collection
     * @return array Informations sur la collection
     */
    public function getCollectionInfo(string $collectionName): array
    {
        try {
            $qdrantUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections/{$collectionName}";

            Log::info('Récupération des informations sur une collection dans Qdrant', [
                'url' => $qdrantUrl,
                'collectionName' => $collectionName,
            ]);

            $response = Http::get($qdrantUrl);

            if ($response->successful()) {
                $info = $response->json('result', []);

                Log::info('Informations sur la collection récupérées avec succès', [
                    'collectionName' => $collectionName,
                    'info' => $info,
                ]);

                return $info;
            } else {
                Log::error('Erreur lors de la récupération des informations sur la collection', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération des informations sur la collection', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Détermine la dimension des vecteurs en fonction du modèle d'embedding
     *
     * @return int Dimension des vecteurs
     */
    protected function getEmbeddingDimension(): int
    {
        // Dimension par défaut pour nomic-embed-text
        $dimension = 768;

        // Ajuster la dimension en fonction du modèle d'embedding
        if (Str::contains($this->embeddingModel, 'bge-m3')) {
            $dimension = 1024; // Dimension pour bge-m3
        } elseif (Str::contains($this->embeddingModel, 'bge-large')) {
            $dimension = 1024;
        } elseif (Str::contains($this->embeddingModel, 'bge-base')) {
            $dimension = 768;
        } elseif (Str::contains($this->embeddingModel, 'bge-small')) {
            $dimension = 384;
        } elseif (Str::contains($this->embeddingModel, 'e5-large')) {
            $dimension = 1024;
        } elseif (Str::contains($this->embeddingModel, 'e5-base')) {
            $dimension = 768;
        } elseif (Str::contains($this->embeddingModel, 'e5-small')) {
            $dimension = 384;
        }

        return $dimension;
    }
}
