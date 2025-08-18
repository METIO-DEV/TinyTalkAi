<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RagService
{
    /** Chunk size */
    protected int $chunkSize = 768;

    /** Chunk overlap */
    protected int $chunkOverlap = 100;

    /**
     * Configuration de l'API Ollama
     */
    protected string $ollamaHost;

    protected string $ollamaPort;

    protected string $embeddingModel;

    /**
     * Configuration de l'API Qdrant
     */
    protected string $qdrantHost;

    protected string $qdrantPort;

    protected string $qdrantCollection;

    /**
     * Constructeur du service
     */
    public function __construct()
    {
        // Configuration Ollama
        $this->ollamaHost = config('services.ollama.host', 'host.docker.internal');
        $this->ollamaPort = config('services.ollama.port', '11434');
        $this->embeddingModel = config('services.ollama.embedding_model', 'nomic-embed-text');

        // Configuration Qdrant
        $this->qdrantHost = config('services.qdrant.host', 'host.docker.internal');
        $this->qdrantPort = config('services.qdrant.port', '6333');
        $this->qdrantCollection = config('services.qdrant.collection', 'docs');
    }

    /**
     * Définit le modèle d'embedding à utiliser
     */
    public function setEmbeddingModel(string $model): void
    {
        $this->embeddingModel = $model;
    }

    /**
     * Définit la collection Qdrant à utiliser
     */
    public function setQdrantCollection(string $collection): void
    {
        $this->qdrantCollection = $collection;
    }

    /**
     * Découpe un texte en chunks
     */
    public function chunkText(string $text): array
    {
        // Nettoyer le texte
        $text = preg_replace('/\s+/', ' ', $text);

        // Découper le texte en phrases
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $currentChunk = '';
        $currentSize = 0;

        foreach ($sentences as $sentence) {
            $sentenceLength = str_word_count($sentence);

            if ($currentSize + $sentenceLength > $this->chunkSize && $currentSize > 0) {
                // Ajouter le chunk actuel à la liste
                $chunks[] = trim($currentChunk);

                // Commencer un nouveau chunk avec chevauchement
                $words = explode(' ', $currentChunk);
                $overlapWords = array_slice($words, -$this->chunkOverlap);
                $currentChunk = implode(' ', $overlapWords).' '.$sentence;
                $currentSize = count($overlapWords) + $sentenceLength;
            } else {
                // Ajouter la phrase au chunk actuel
                $currentChunk .= ' '.$sentence;
                $currentSize += $sentenceLength;
            }
        }

        // Ajouter le dernier chunk s'il n'est pas vide
        if (! empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Génère un embedding pour un texte donné
     */
    public function generateEmbedding(string $text, ?string $model = null): array
    {
        try {

            $ollamaUrl = "http://{$this->ollamaHost}:{$this->ollamaPort}/api/embeddings";

            $response = Http::post($ollamaUrl, [
                'model' => $model,
                'prompt' => $text,
                'options' => [
                    'temperature' => 0.0,
                ],
            ]);

            if ($response->successful()) {
                // Log::info('Embedding généré avec succès', [
                //     'text' => $text,
                //     'embedding' => $response->json('embedding', []),
                // ]);
                return $response->json('embedding', []);
            } else {
                Log::error('Erreur lors de la génération de l\'embedding: '.$response->body());

                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception lors de la génération de l\'embedding: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Crée la collection Qdrant si elle n'existe pas
     */
    public function ensureCollectionExists(int $vectorSize = 768): bool
    {
        try {
            $qdrantUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections/{$this->qdrantCollection}";

            Log::info("Vérification de l'existence de la collection Qdrant", [
                'url' => $qdrantUrl,
                'collection' => $this->qdrantCollection,
                'host' => $this->qdrantHost,
                'port' => $this->qdrantPort,
            ]);

            // Vérifier si la collection existe
            $response = Http::get($qdrantUrl);

            if ($response->successful()) {
                Log::info("Collection {$this->qdrantCollection} existe déjà");

                return true;
            }

            Log::info("La collection n'existe pas, tentative de création", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Créer la collection si elle n'existe pas
            // URL correcte selon la documentation Qdrant: PUT /collections/:collection_name
            $createUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections/{$this->qdrantCollection}";

            $payload = [
                'vectors' => [
                    'size' => $vectorSize,
                    'distance' => 'Cosine',
                ],
            ];

            Log::info('Création de la collection Qdrant (selon doc officielle)', [
                'url' => $createUrl,
                'method' => 'PUT',
                'payload' => $payload,
            ]);

            // Utiliser PUT selon la documentation officielle
            $response = Http::put($createUrl, $payload);

            if ($response->successful()) {
                Log::info("Collection {$this->qdrantCollection} créée avec succès", [
                    'response' => $response->json(),
                ]);

                return true;
            } else {
                Log::error('Erreur lors de la création de la collection', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers(),
                ]);

                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception lors de la création de la collection', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return false;
        }
    }

    /**
     * Insère un document dans Qdrant
     */
    public function upsertDocument(string $documentId, string $chunk, array $embedding, array $metadata = []): bool
    {
        try {
            if (empty($embedding)) {
                Log::error("Embedding vide pour le document {$documentId}");

                return false;
            }

            // Générer un UUID valide sans modification
            // Qdrant n'accepte que des UUID ou des entiers non signés comme ID
            $pointId = Str::uuid()->toString();

            // Ajouter le hash du chunk aux métadonnées pour pouvoir identifier les doublons
            $chunkHash = md5($chunk);
            $metadata['chunk_hash'] = $chunkHash;
            if (! empty($documentId)) {
                $metadata['original_id'] = $documentId.'_'.$chunkHash;
            }

            $qdrantUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections/{$this->qdrantCollection}/points";

            // Format correct selon la documentation Qdrant
            // https://qdrant.tech/documentation/api-reference/points/#upsert-points
            $payload = [
                'points' => [
                    [
                        'id' => $pointId,
                        'vector' => $embedding,
                        'payload' => array_merge([
                            'text' => $chunk,
                            'document_id' => $documentId,
                        ], $metadata),
                    ],
                ],
            ];

            Log::info("Tentative d'insertion du document dans Qdrant", [
                'url' => $qdrantUrl,
                'pointId' => $pointId,
                'payload_structure' => array_keys($payload),
            ]);

            // Utiliser PUT selon la documentation officielle
            $response = Http::put($qdrantUrl, $payload);

            if ($response->successful()) {
                Log::info("Document {$pointId} inséré avec succès", [
                    'response' => $response->json(),
                ]);

                return true;
            } else {
                Log::error("Erreur lors de l'insertion du document", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }
        } catch (\Exception $e) {
            Log::error("Exception lors de l'insertion du document", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Recherche les documents les plus similaires à une requête
     *
     * @param  string  $query  La requête à rechercher
     * @param  int  $limit  Nombre maximum de résultats à retourner
     * @param  array|null  $documentIds  Liste des IDs de documents à considérer (filtrage par conversation)
     * @param  string|null  $collection  Nom de la collection à utiliser (si différent de la collection par défaut)
     * @return array Liste des documents similaires avec leurs scores
     */
    public function searchSimilarDocuments(string $query, int $limit = 4, ?array $documentIds = null, ?string $collection = null): array
    {
        try {
            // Si une collection est spécifiée, l'utiliser temporairement
            $originalCollection = null;
            if ($collection !== null) {
                $originalCollection = $this->qdrantCollection;
                $this->qdrantCollection = $collection;
                
                Log::info('Utilisation temporaire de la collection pour la recherche', [
                    'collection' => $collection,
                    'query' => $query,
                ]);
            }

            // Log pour vérifier quelle collection est effectivement utilisée
            Log::info('Collection utilisée pour la recherche', [
                'collection' => $this->qdrantCollection,
                'original_collection' => $originalCollection,
                'param_collection' => $collection,
                'query' => $query,
            ]);

            // Générer l'embedding de la requête
            $embedding = $this->generateEmbedding($query, $this->embeddingModel);

            if (empty($embedding)) {
                Log::error("Impossible de générer l'embedding pour la recherche", [
                    'query' => $query,
                    'model' => $this->embeddingModel,
                ]);

                // Restaurer la collection originale si nécessaire
                if ($originalCollection !== null) {
                    $this->qdrantCollection = $originalCollection;
                }

                return [];
            }

            $qdrantUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections/{$this->qdrantCollection}/points/search";

            $requestData = [
                'vector' => $embedding,
                'limit' => $limit,
                'with_payload' => true,
                'with_vector' => false,
                'score_threshold' => 0.20, // Seuil minimal de score pour filtrer les résultats non pertinents
            ];

            // Ajouter un filtre sur les IDs de documents si spécifié
            if (! empty($documentIds)) {
                $requestData['filter'] = [
                    'must' => [
                        [
                            'key' => 'document_id',
                            'match' => [
                                'any' => $documentIds,
                            ],
                        ],
                    ],
                ];

                Log::info('Recherche RAG avec filtrage par documents', [
                    'document_ids' => $documentIds,
                    'count' => count($documentIds),
                    'query' => $query,
                ]);
            } else {
                Log::warning('Recherche RAG sans filtrage par documents - tous les documents seront considérés', [
                    'query' => $query,
                ]);
            }

            Log::info('Requête Qdrant', [
                'url' => $qdrantUrl,
                'request_data' => $requestData, 
            ]);

            $response = Http::post($qdrantUrl, $requestData);

            if ($response->successful()) {
                $results = $response->json('result', []);

                Log::info('Résultats bruts de Qdrant', [
                    'count' => count($results),
                    'response' => $response->json(),
                ]);

                // Extraire les textes et les scores
                $documents = [];
                foreach ($results as $result) {
                    $score = $result['score'] ?? 0;

                    // Ignorer les résultats avec un score trop bas
                    if ($score < 0.20) {
                        continue;
                    }

                    $documents[] = [
                        'text' => $result['payload']['text'] ?? '',
                        'document_id' => $result['payload']['document_id'] ?? '',
                        'score' => $score,
                        'metadata' => array_diff_key($result['payload'] ?? [], ['text' => '', 'document_id' => '']),
                    ];

                    Log::info('Document pertinent trouvé', [
                        'document_id' => $result['payload']['document_id'] ?? 'inconnu',
                        'score' => $score,
                        'text_preview' => substr($result['payload']['text'] ?? '', 0, 100).'...',
                    ]);
                }

                // Restaurer la collection originale si nécessaire
                if ($originalCollection !== null) {
                    $this->qdrantCollection = $originalCollection;
                }

                return $documents;
            } else {
                Log::error('Erreur lors de la recherche de documents: '.$response->body(), [
                    'status' => $response->status(),
                    'request_data' => $requestData,
                ]);

                // Restaurer la collection originale si nécessaire
                if ($originalCollection !== null) {
                    $this->qdrantCollection = $originalCollection;
                }

                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception lors de la recherche de documents: '.$e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Restaurer la collection originale si nécessaire
            if (isset($originalCollection)) {
                $this->qdrantCollection = $originalCollection;
            }

            return [];
        }
    }

    /**
     * Traite un document pour le RAG
     */
    public function processDocument(string $documentId, string $content, array $metadata = [], ?string $collection = null): bool
    {
        try {
            // Si une collection est spécifiée, l'utiliser temporairement
            $originalCollection = null;
            if ($collection !== null) {
                $originalCollection = $this->qdrantCollection;
                $this->qdrantCollection = $collection;
            }

            Log::info('Traitement du document', [
                'documentId' => $documentId,
                'contentLength' => strlen($content),
                'metadata' => $metadata,
                'collection' => $this->qdrantCollection,
            ]);

            // Vérifier que la collection existe, sinon la créer
            $this->ensureCollectionExists();

            // Découper le contenu en chunks
            $chunks = $this->chunkText($content);
            Log::info('Document découpé en chunks', ['count' => count($chunks)]);

            $successCount = 0;

            foreach ($chunks as $index => $chunk) {
                // Générer l'embedding pour ce chunk (toujours avec bge-m3)
                $embedding = $this->generateEmbedding($chunk, $this->embeddingModel);

                if (empty($embedding)) {
                    Log::error("Échec de génération d'embedding pour le chunk {$index}");

                    continue;
                }

                // Ajouter des métadonnées spécifiques au chunk
                $chunkMetadata = array_merge($metadata, [
                    'chunk_index' => $index,
                    'total_chunks' => count($chunks),
                ]);

                // Insérer dans Qdrant
                $success = $this->upsertDocument($documentId, $chunk, $embedding, $chunkMetadata);

                if ($success) {
                    $successCount++;
                }
            }

            Log::info('Traitement du document terminé', [
                'documentId' => $documentId,
                'totalChunks' => count($chunks),
                'successfulChunks' => $successCount,
                'collection' => $this->qdrantCollection,
            ]);

            // Restaurer la collection originale si nécessaire
            if ($originalCollection !== null) {
                $this->qdrantCollection = $originalCollection;
            }

            return $successCount > 0;
        } catch (\Exception $e) {
            // Restaurer la collection originale en cas d'erreur
            if (isset($originalCollection)) {
                $this->qdrantCollection = $originalCollection;
            }

            Log::error('Exception lors du traitement du document', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Prépare un prompt enrichi avec le contexte pour le LLM
     */
    public function prepareEnrichedPrompt(string $question, array $contextDocuments): string
    {
        $context = '';

        foreach ($contextDocuments as $index => $doc) {
            $context .= 'Contexte '.($index + 1).":\n".$doc['text']."\n\n";
        }

        return <<<EOT
Voici des extraits de documents pertinents pour répondre à la question:

{$context}

En utilisant uniquement ces informations et vos connaissances générales, répondez à la question suivante:
Question: {$question}

Réponse:
EOT;
    }

    /**
     * Récupère la liste des modèles disponibles pour les embeddings
     *
     * @return array Liste des modèles disponibles pour les embeddings
     */
    public function getAvailableEmbeddingModels(): array
    {
        try {
            $ollamaHost = config('services.ollama.host', 'localhost');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = "http://{$ollamaHost}:{$ollamaPort}/api/tags";

            Log::info("Récupération des modèles depuis: {$ollamaUrl}");

            $response = Http::timeout(10)->get($ollamaUrl);

            if (! $response->successful()) {
                Log::error('Erreur lors de la récupération des modèles: '.$response->body());

                return [$this->embeddingModel]; // Retourne au moins le modèle par défaut
            }

            // Log de la réponse brute pour diagnostic
            Log::info("Réponse brute de l'API Ollama:", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $models = $response->json('models', []);

            // Log des modèles récupérés
            Log::info('Modèles récupérés:', [
                'count' => count($models),
                'models' => array_map(function ($model) {
                    return $model['name'] ?? 'unknown';
                }, $models),
            ]);

            // Filtrer les modèles qui peuvent être utilisés pour les embeddings
            $embeddingModels = [];

            foreach ($models as $model) {
                $name = $model['name'] ?? '';
                if (! empty($name)) {
                    $isEmbedding = $this->isEmbeddingModel($name);
                    Log::info("Vérification du modèle: {$name}", [
                        'isEmbeddingModel' => $isEmbedding,
                    ]);

                    if ($isEmbedding) {
                        $embeddingModels[] = $name;
                    }
                }
            }

            // Log des modèles d'embedding filtrés
            Log::info("Modèles d'embedding filtrés:", [
                'count' => count($embeddingModels),
                'models' => $embeddingModels,
            ]);

            // Si aucun modèle d'embedding n'est trouvé, retourner au moins le modèle par défaut
            if (empty($embeddingModels)) {
                Log::warning("Aucun modèle d'embedding trouvé, utilisation du modèle par défaut: {$this->embeddingModel}");

                return [$this->embeddingModel];
            }

            return $embeddingModels;
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération des modèles: '.$e->getMessage());

            return [$this->embeddingModel]; // Retourne au moins le modèle par défaut
        }
    }

    /**
     * Détermine si un modèle est un modèle d'embedding
     *
     * @param  string  $modelName  Nom du modèle à vérifier
     * @return bool True si c'est un modèle d'embedding, false sinon
     */
    public function isEmbeddingModel(string $modelName): bool
    {
        // Liste des modèles connus pour être des modèles d'embedding
        $knownEmbeddingModels = [
            'nomic-embed-text',
            'nomic-embed',
            'all-minilm',
            'e5-small',
            'e5-base',
            'e5-large',
            'bge-small',
            'bge-base',
            'bge-large',
            'bge-m3',  // Ajout explicite de bge-m3
            'jina-embeddings',
            'sentence-transformers',
            'text-embedding',
        ];

        // Vérifier si le nom du modèle contient un des termes connus pour les embeddings
        $embeddingKeywords = ['embed', 'embedding', 'encoder', 'e5', 'bge', 'gte', 'minilm', 'm3'];

        // Vérifier si le modèle est dans la liste des modèles connus
        foreach ($knownEmbeddingModels as $embeddingModel) {
            if (stripos($modelName, $embeddingModel) !== false) {
                return true;
            }
        }

        // Vérifier si le nom du modèle contient un mot-clé d'embedding
        foreach ($embeddingKeywords as $keyword) {
            if (stripos($modelName, $keyword) !== false) {
                return true;
            }
        }

        // Par défaut, considérer que ce n'est pas un modèle d'embedding
        return false;
    }

    /**
     * Récupère la liste des modèles disponibles pour la génération (non-embedding)
     *
     * @return array Liste des modèles disponibles pour la génération
     */
    public function getAvailableGenerationModels(): array
    {
        try {
            $ollamaHost = config('services.ollama.host', 'localhost');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = "http://{$ollamaHost}:{$ollamaPort}/api/tags";

            $response = Http::timeout(10)->get($ollamaUrl);

            if (! $response->successful()) {
                Log::error('Erreur lors de la récupération des modèles: '.$response->body());

                return []; // Retourne une liste vide en cas d'erreur
            }

            $models = $response->json('models', []);

            // Filtrer les modèles qui peuvent être utilisés pour la génération (non-embedding)
            $generationModels = [];

            foreach ($models as $model) {
                $name = $model['name'] ?? '';
                if (! empty($name) && ! $this->isEmbeddingModel($name)) {
                    $generationModels[] = $name;
                }
            }

            return $generationModels;
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération des modèles: '.$e->getMessage());

            return []; // Retourne une liste vide en cas d'erreur
        }
    }

    /**
     * Liste les points existants dans Qdrant (méthode de diagnostic)
     */
    public function listPoints(int $limit = 10): array
    {
        try {
            $qdrantUrl = "http://{$this->qdrantHost}:{$this->qdrantPort}/collections/{$this->qdrantCollection}/points/scroll";

            Log::info('Tentative de listage des points dans Qdrant', [
                'url' => $qdrantUrl,
                'limit' => $limit,
            ]);

            $response = Http::post($qdrantUrl, [
                'limit' => $limit,
                'with_payload' => true,
                'with_vector' => false,
            ]);

            if ($response->successful()) {
                $results = $response->json('result.points', []);

                Log::info('Points récupérés avec succès', [
                    'count' => count($results),
                    'response' => $response->json(),
                ]);

                return $results;
            } else {
                Log::error('Erreur lors du listage des points', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception lors du listage des points', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }
}
