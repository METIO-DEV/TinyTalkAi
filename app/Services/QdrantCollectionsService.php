<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\EmbeddingModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $this->embeddingModel = EmbeddingModel::getActiveModel();

        Log::info('QdrantCollectionsService initialized', [
            'embedding_model' => $this->embeddingModel,
            'qdrant_host' => $this->qdrantHost,
            'qdrant_port' => $this->qdrantPort,
        ]);
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

            $response = Http::timeout(10)->get($qdrantUrl);

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

            $response = Http::timeout(30)->put($qdrantUrl, [
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

            $response = Http::timeout(30)->delete($qdrantUrl);

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

            $response = Http::timeout(10)->get($qdrantUrl);

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

            $response = Http::timeout(10)->get($qdrantUrl);

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

    public function getDocumentStats(string $collectionName): array
    {
        $stats = [
            'documentCount' => 0,
            'chunkCount' => 0,
            'documentTitles' => [],
        ];

        try {
            $info = $this->getCollectionInfo($collectionName);
            $stats['chunkCount'] = (int) ($info['points_count'] ?? $info['vectors_count'] ?? 0);

            $qdrantUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections/{$collectionName}/points/scroll";
            $documentIds = [];
            $documentTitles = [];
            $offset = null;

            do {
                $payload = [
                    'limit' => 256,
                    'with_payload' => true,
                    'with_vector' => false,
                ];

                if ($offset !== null) {
                    $payload['offset'] = $offset;
                }

                $response = Http::timeout(10)->post($qdrantUrl, $payload);

                if (! $response->successful()) {
                    Log::warning('Impossible de compter les documents Qdrant', [
                        'collectionName' => $collectionName,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    break;
                }

                foreach ($response->json('result.points', []) as $point) {
                    $payload = $point['payload'] ?? [];
                    $documentId = $payload['document_id'] ?? null;

                    if (is_string($documentId) && $documentId !== '') {
                        $documentIds[$documentId] = true;

                        if (! isset($documentTitles[$documentId])) {
                            $title = $payload['title'] ?? $payload['filename'] ?? $documentId;
                            $documentTitles[$documentId] = is_string($title) && $title !== '' ? $title : $documentId;
                        }
                    }
                }

                $offset = $response->json('result.next_page_offset');
            } while ($offset !== null);

            $stats['documentCount'] = count($documentIds);
            $stats['documentTitles'] = array_slice(array_values($documentTitles), 0, 3);
        } catch (\Throwable $e) {
            Log::warning('Erreur lors du calcul des statistiques de collection', [
                'collectionName' => $collectionName,
                'message' => $e->getMessage(),
            ]);
        }

        return $stats;
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

        return $dimension;
    }

    /**
     * Synchronise les collections de Qdrant vers la base de données
     *
     * @return array Résultat de la synchronisation [success, message, count]
     */
    public function syncCollectionsToDatabase(): array
    {
        try {
            Log::info('Début de la synchronisation des collections Qdrant vers la base de données');

            // Récupérer les collections depuis Qdrant
            $qdrantCollections = $this->listCollections();

            if (empty($qdrantCollections)) {
                Log::info('Aucune collection trouvée dans Qdrant');

                return [
                    'success' => true,
                    'message' => 'Aucune collection trouvée dans Qdrant',
                    'count' => 0,
                ];
            }

            $count = 0;

            // Pour chaque collection dans Qdrant
            foreach ($qdrantCollections as $collectionName) {
                // Vérifier si la collection existe déjà en base de données
                $existingCollection = Collection::where('name', $collectionName)->first();

                if (! $existingCollection) {
                    // Récupérer les informations de la collection depuis Qdrant
                    $collectionInfo = $this->getCollectionInfo($collectionName);

                    // Créer la collection en base de données
                    Collection::create([
                        'name' => $collectionName,
                        'description' => 'Collection importée depuis Qdrant',
                        'is_active' => true,
                        'metadata' => ! empty($collectionInfo) ? json_encode($collectionInfo) : null,
                    ]);

                    $count++;
                    Log::info("Collection '{$collectionName}' importée avec succès");
                } else {
                    Log::info("Collection '{$collectionName}' déjà existante en base de données");
                }
            }

            Log::info("Synchronisation terminée. {$count} collections importées");

            return [
                'success' => true,
                'message' => "{$count} collections importées avec succès",
                'count' => $count,
            ];

        } catch (\Exception $e) {
            Log::error('Erreur lors de la synchronisation des collections: '.$e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la synchronisation: '.$e->getMessage(),
                'count' => 0,
            ];
        }
    }
}
