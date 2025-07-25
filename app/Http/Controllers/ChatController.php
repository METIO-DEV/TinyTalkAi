<?php

namespace App\Http\Controllers;

use App\Models\AIModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationMemoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur responsable de la gestion des interactions avec l'API Ollama
 *
 * Ce contrôleur gère deux aspects principaux :
 * 1. L'affichage de l'interface de chat avec la liste des modèles disponibles
 * 2. L'envoi des messages utilisateur à Ollama et la récupération des réponses
 */
class ChatController extends Controller
{
    /**
     * Service de gestion de la mémoire conversationnelle
     */
    protected ConversationMemoryService $memoryService;
    
    /**
     * Constructeur du contrôleur
     * 
     * @param ConversationMemoryService $memoryService Service de mémoire conversationnelle
     */
    public function __construct(ConversationMemoryService $memoryService)
    {
        $this->memoryService = $memoryService;
    }

    /**
     * Affiche la page de chat avec la liste des modèles disponibles
     *
     * Cette méthode est appelée lorsque l'utilisateur accède à la route '/chat'
     * Elle récupère la liste des modèles LLM disponibles via l'API Ollama
     * et les transmet à la vue pour affichage dans un menu déroulant
     *
     * @return \Illuminate\Contracts\View\View La vue 'chat' avec les modèles disponibles
     */
    public function index()
    {
        // Récupérer la liste des modèles disponibles via Ollama
        $models = $this->getAvailableModels();

        // Récupérer les conversations de l'utilisateur connecté
        $conversations = [];
        if (auth()->check()) {
            $conversations = Conversation::where('user_id', auth()->id())
                ->orderBy('updated_at', 'desc')
                ->get();
        }

        // Passer les modèles et les conversations à la vue
        return view('chat', [
            'models' => $models,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Récupère la liste des modèles LLM disponibles via l'API Ollama
     *
     * Cette méthode privée interroge l'endpoint '/api/tags' d'Ollama pour obtenir
     * la liste des modèles LLM téléchargés et disponibles localement.
     *
     * Particularités techniques :
     * - Utilise la configuration depuis config/services.php avec des valeurs par défaut
     * - Le timeout est limité à 5 secondes pour éviter de bloquer l'interface
     * - Gestion complète des erreurs avec journalisation pour faciliter le débogage
     * - Retourne un tableau vide en cas d'échec pour éviter les erreurs dans la vue
     *
     * @return array Liste des modèles disponibles ou tableau vide si erreur
     */
    private function getAvailableModels()
    {
        try {
            // Récupération des paramètres de configuration avec valeurs par défaut
            // 'host.docker.internal' permet d'accéder à la machine hôte depuis un conteneur Docker
            // Cette approche facilite le déploiement dans différents environnements
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434'); // Port par défaut d'Ollama
            $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/tags'; // Endpoint pour lister les modèles

            // Journalisation pour faciliter le débogage et le monitoring
            Log::info('Tentative de connexion à Ollama: '.$ollamaUrl);

            // Requête HTTP avec timeout court pour éviter de bloquer l'interface utilisateur
            $response = Http::timeout(5)->get($ollamaUrl);

            // Vérification du succès de la requête (code 2xx)
            if ($response->successful()) {
                Log::info('Connexion à Ollama réussie');

                // Extraction des modèles depuis la réponse JSON
                // L'API Ollama renvoie les modèles sous la clé 'models'
                return $response->json('models');
            }

            // Journalisation en cas d'échec avec le code d'état HTTP
            Log::error('Échec de la connexion à Ollama: '.$response->status());

            // Retour d'un tableau vide pour éviter les erreurs dans la vue
            return [];
        } catch (\Exception $e) {
            // Capture de toutes les exceptions possibles (réseau, timeout, etc.)
            Log::error('Erreur lors de la récupération des modèles Ollama: '.$e->getMessage());

            // Retour d'un tableau vide pour assurer la robustesse
            return [];
        }
    }

    /**
     * Synchronise les modèles disponibles avec la base de données
     *
     * @param  array  $ollamaModels  Liste des modèles récupérés depuis Ollama
     * @return void
     */
    private function syncModelsWithDatabase($ollamaModels)
    {
        try {
            if (empty($ollamaModels)) {
                return;
            }

            // Marquer tous les modèles comme inactifs
            AIModel::query()->update(['is_active' => false]);

            foreach ($ollamaModels as $model) {
                // Chercher si le modèle existe déjà
                $aiModel = AIModel::where('full_name', $model['name'])->first();

                if ($aiModel) {
                    // Mettre à jour le modèle existant
                    $aiModel->update([
                        'is_active' => true,
                        'size' => $model['size'] ?? null,
                        'last_synced_at' => now(),
                    ]);
                } else {
                    // Créer un nouveau modèle
                    $nameParts = explode(':', $model['name']);
                    $name = $nameParts[0];

                    AIModel::create([
                        'name' => $name,
                        'full_name' => $model['name'],
                        'size' => $model['size'] ?? null,
                        'is_active' => true,
                        'last_synced_at' => now(),
                    ]);
                }
            }

            Log::info('Synchronisation des modèles réussie');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la synchronisation des modèles: '.$e->getMessage());
        }
    }

    /**
     * Envoie un message utilisateur à Ollama et récupère la réponse générée par le LLM
     *
     * Cette méthode est appelée via une requête AJAX depuis l'interface de chat.
     * Elle valide les entrées, envoie le message à Ollama, et sauvegarde la conversation en BDD.
     *
     * @param  Request  $request  Requête HTTP contenant le message et les paramètres
     * @return \Illuminate\Http\JsonResponse Réponse JSON contenant la réponse du LLM ou un message d'erreur
     */
    public function sendMessage(Request $request)
    {
        try {
            // Validation des données d'entrée
            $request->validate([
                'message' => 'required|string',
                'model' => 'required|string',
                'temperature' => 'required|numeric|min:0|max:2',
                'max_tokens' => 'required|integer|min:10|max:4096',
                'conversation_id' => 'nullable|integer|exists:conversations,id',
            ]);

            // Récupération des paramètres
            $userMessage = $request->input('message');
            $modelName = $request->input('model');
            $temperature = $request->input('temperature');
            $maxTokens = $request->input('max_tokens');
            $conversationId = $request->input('conversation_id');

            // Paramètres pour l'API Ollama
            $settings = [
                'model' => $modelName,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ];

            // Gestion de la conversation
            $conversation = null;
            $isNewConversation = false;

            if (Auth::check()) {
                if ($conversationId) {
                    // Récupérer une conversation existante
                    $conversation = Conversation::where('id', $conversationId)
                        ->where('user_id', Auth::id())
                        ->first();
                }

                if (! $conversation) {
                    // Créer une nouvelle conversation
                    $conversation = Conversation::create([
                        'user_id' => Auth::id(),
                        'title' => substr($userMessage, 0, 50).(strlen($userMessage) > 50 ? '...' : ''),
                        'model_name' => $modelName,
                    ]);
                    $isNewConversation = true;
                }

                // Sauvegarder le message de l'utilisateur
                Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $userMessage,
                    'settings' => $settings,
                ]);
            }

            // Récupérer la fenêtre locale (contexte des messages précédents)
            $messages = [];
            if ($conversation) {
                $messages = $this->memoryService->getLocalWindow($conversation->id);
            } else {
                // Si pas de conversation (utilisateur non authentifié), utiliser uniquement le message actuel
                $messages = [
                    [
                        'role' => 'user',
                        'content' => $userMessage
                    ]
                ];
            }

            // Configuration de l'API Ollama
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/chat'; // Endpoint pour lister les modèles

            Log::info('Envoi du message à Ollama: '.$ollamaUrl);

            // Préparation du prompt avec le contexte
            $response = Http::timeout(60)->post($ollamaUrl, [
                'model' => $modelName,
                'messages' => $messages, // Utilisation de la fenêtre locale comme contexte
                'options' => [
                    'temperature' => (float) $temperature,
                    'max_tokens' => (int) $maxTokens,
                ],
                'stream' => false,
            ]);

            if ($response->successful()) {
                Log::info('Réponse reçue d\'Ollama avec succès');

                // Récupérer la réponse depuis le format de l'API /api/chat
                $aiResponse = $response->json('message.content');

                // Sauvegarder la réponse de l'assistant
                if (Auth::check() && $conversation) {
                    Message::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'assistant',
                        'content' => $aiResponse,
                        'settings' => $settings,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'response' => $aiResponse,
                    'conversation_id' => $conversation ? $conversation->id : null,
                    'is_new_conversation' => $isNewConversation,
                ]);
            }

            Log::error('Échec de la communication avec Ollama: '.$response->status());
            Log::error($response->body());

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la communication avec Ollama: '.$response->status(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi du message à Ollama: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la communication avec Ollama: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupère l'historique des messages d'une conversation
     *
     * @param  int  $conversationId  ID de la conversation
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConversationHistory($conversationId)
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', Auth::id())
            ->with('messages')
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Conversation non trouvée'], 404);
        }

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
            'messages' => $conversation->messages,
        ]);
    }

    /**
     * Récupère la liste des conversations de l'utilisateur
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConversations()
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $conversations = Conversation::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Supprime une conversation et tous ses messages associés
     *
     * @param  int  $conversationId  ID de la conversation à supprimer
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteConversation($conversationId)
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Conversation non trouvée ou accès non autorisé'], 404);
        }

        // Suppression de la conversation (les messages seront supprimés automatiquement si la relation est configurée avec onDelete cascade)
        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation supprimée avec succès',
        ]);
    }
}
