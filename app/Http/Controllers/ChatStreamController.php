<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationMemoryService;
use App\Services\OllamaHealthService;
use App\Services\RagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatStreamController extends Controller
{
    protected ConversationMemoryService $memoryService;

    protected RagService $ragService;

    protected OllamaHealthService $ollamaHealth;

    public function __construct(ConversationMemoryService $memoryService, RagService $ragService, OllamaHealthService $ollamaHealth)
    {
        $this->memoryService = $memoryService;
        $this->ragService = $ragService;
        $this->ollamaHealth = $ollamaHealth;
    }

    public function stream(Request $request)
    {
        // Vérifier l'authentification
        if (! Auth::check()) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        // Valider les données de la requête
        $validated = $request->validate([
            'model' => 'required|string',
            'messages' => 'required|array',
            'conversationId' => 'nullable|integer',
            'ragEnabled' => 'boolean',
            'selectedCollection' => 'nullable|string',
            'temperature' => 'numeric|min:0|max:2',
            'maxTokens' => 'integer|min:1|max:8192',
        ]);

        $ollamaStatus = $this->ollamaHealth->status();
        if (! $ollamaStatus['available']) {
            return response()->json(['message' => $ollamaStatus['message']], 503);
        }

        return new StreamedResponse(function () use ($validated) {
            // Configuration des headers SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Pour nginx

            try {
                // Préparer les données pour l'API Ollama
                $ollamaHost = config('services.ollama.host', 'localhost');
                $ollamaPort = config('services.ollama.port', '11434');
                $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/chat';
                $thinkingEnabled = $this->modelSupportsThinking($validated['model']);

                $payload = [
                    'model' => $validated['model'],
                    'messages' => $validated['messages'],
                    'options' => [
                        'temperature' => (float) ($validated['temperature'] ?? 0.7),
                        'max_tokens' => (int) ($validated['maxTokens'] ?? 2048),
                    ],
                    'stream' => true,
                ];

                if ($thinkingEnabled) {
                    $payload['think'] = true;
                }

                Log::info('Démarrage du streaming vers Ollama', [
                    'url' => $ollamaUrl,
                    'model' => $validated['model'],
                    'message_count' => count($validated['messages']),
                    'thinking_enabled' => $thinkingEnabled,
                ]);

                // Initialiser cURL pour le streaming
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $ollamaUrl,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                    ],
                    CURLOPT_WRITEFUNCTION => [$this, 'handleStreamChunk'],
                    CURLOPT_TIMEOUT => 600,
                    CURLOPT_CONNECTTIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_BUFFERSIZE => 128,
                ]);

                // Variables pour accumuler la réponse complète
                $this->completeResponse = '';
                $this->conversationId = isset($validated['conversationId'])
                    ? (int) $validated['conversationId']
                    : null;
                $this->model = $validated['model'];
                $this->temperature = $validated['temperature'] ?? 0.7;
                $this->maxTokens = $validated['maxTokens'] ?? 2048;
                $this->ragEnabled = $validated['ragEnabled'] ?? false;
                $this->thinkingOpen = false;

                // Exécuter la requête cURL
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($result === false || ! empty($error)) {
                    if (connection_aborted() || str_contains(strtolower($error), 'write')) {
                        Log::info('Streaming arrêté par le client', [
                            'model' => $validated['model'],
                            'conversation_id' => $this->conversationId,
                        ]);

                        return;
                    }

                    $this->sendSSEEvent('error', ['message' => 'Erreur de connexion: '.$error]);
                    Log::error('Erreur cURL lors du streaming', ['error' => $error]);
                } elseif ($httpCode !== 200) {
                    $this->sendSSEEvent('error', ['message' => 'Erreur HTTP: '.$httpCode]);
                    Log::error('Erreur HTTP lors du streaming', ['code' => $httpCode]);
                } else {
                    // Envoyer l'événement de fin avec les statistiques
                    $this->sendSSEEvent('complete', [
                        'response' => $this->completeResponse,
                        'conversationId' => $this->conversationId,
                        'tokens' => $this->extractTokensFromLastResponse(),
                    ]);
                }

            } catch (\Exception $e) {
                $this->sendSSEEvent('error', ['message' => 'Erreur: '.$e->getMessage()]);
                Log::error('Exception lors du streaming', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // Fermer la connexion SSE
            $this->sendSSEEvent('close', []);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // Variables de classe pour stocker l'état
    private string $completeResponse = '';

    private ?int $conversationId = null;

    private string $model = '';

    private float $temperature = 0.7;

    private int $maxTokens = 2048;

    private bool $ragEnabled = false;

    private bool $thinkingOpen = false;

    /**
     * Fonction de callback pour traiter les chunks de données du streaming
     */
    private function handleStreamChunk($ch, $data)
    {
        if (connection_aborted()) {
            return 0;
        }

        $lines = explode("\n", $data);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            try {
                $json = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                if (! empty($json['message']['thinking'])) {
                    $thinking = $json['message']['thinking'];
                    $chunk = $this->thinkingOpen ? $thinking : '<think>'.$thinking;
                    $this->thinkingOpen = true;

                    $this->appendAndSendChunk($chunk);
                }

                if (isset($json['message']['content']) && $json['message']['content'] !== '') {
                    $content = $json['message']['content'];
                    if ($this->thinkingOpen) {
                        $content = '</think>'.$content;
                        $this->thinkingOpen = false;
                    }

                    $this->appendAndSendChunk($content);
                }

                // Si c'est le dernier message, traiter les statistiques
                if (isset($json['done']) && $json['done'] === true) {
                    if ($this->thinkingOpen) {
                        $this->appendAndSendChunk('</think>');
                        $this->thinkingOpen = false;
                    }

                    $this->handleFinalResponse($json);
                }

                if (connection_aborted()) {
                    return 0;
                }

            } catch (\Exception $e) {
                Log::error('Erreur lors du traitement du chunk', [
                    'error' => $e->getMessage(),
                    'line' => $line,
                ]);
            }
        }

        // Forcer l'envoi immédiat des données
        if (ob_get_level()) {
            ob_flush();
        }
        flush();

        return connection_aborted() ? 0 : strlen($data);
    }

    private function appendAndSendChunk(string $content): void
    {
        $this->completeResponse .= $content;

        // Sauvegarder le chunk en temps réel dans la BD
        $conversation = Conversation::where('id', $this->conversationId)
            ->where('user_id', Auth::id())
            ->first();

        if ($conversation) {
            $this->updateAssistantMessageInDatabase($conversation, $this->completeResponse);
        }

        // Émettre l'événement chunk
        $this->sendSSEEvent('chunk', [
            'content' => $content,
            'conversationId' => $this->conversationId,
        ]);
    }

    /**
     * Traiter la réponse finale avec les statistiques
     */
    private function handleFinalResponse(array $json)
    {
        try {
            // Extraire les informations de tokens
            $promptTokens = $json['prompt_eval_count'] ?? 0;
            $responseTokens = $json['eval_count'] ?? 0;
            $totalTokens = $promptTokens + $responseTokens;

            Log::info('Réponse streaming complète', [
                'response_length' => strlen($this->completeResponse),
                'total_tokens' => $totalTokens,
                'conversation_id' => $this->conversationId,
            ]);

            // Sauvegarder la réponse si on a une conversation
            if (Auth::check() && $this->conversationId) {
                $conversation = Conversation::where('id', $this->conversationId)
                    ->where('user_id', Auth::id())
                    ->first();

                if ($conversation) {
                    // Mettre à jour le compteur de tokens
                    $conversation->increment('tokens', $totalTokens);

                    // Sauvegarder le message de l'assistant
                    Message::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'assistant',
                        'content' => $this->completeResponse,
                        'settings' => [
                            'model' => $this->model,
                            'temperature' => $this->temperature,
                            'max_tokens' => $this->maxTokens,
                            'tokens_used' => $totalTokens,
                            'rag_enabled' => $this->ragEnabled,
                            'streaming' => true,
                        ],
                    ]);

                    Log::info('Message assistant sauvegardé', [
                        'conversation_id' => $conversation->id,
                        'tokens' => $totalTokens,
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Erreur lors de la sauvegarde de la réponse finale', [
                'error' => $e->getMessage(),
                'conversation_id' => $this->conversationId,
            ]);
        }
    }

    /**
     * Envoyer un événement Server-Sent Event
     */
    private function sendSSEEvent(string $event, array $data)
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data)."\n\n";

        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    private function modelSupportsThinking(string $model): bool
    {
        try {
            $ollamaHost = config('services.ollama.host', 'localhost');
            $ollamaPort = config('services.ollama.port', '11434');
            $response = Http::timeout(3)->post("http://{$ollamaHost}:{$ollamaPort}/api/show", [
                'name' => $model,
            ]);

            if (! $response->successful()) {
                return false;
            }

            return in_array('thinking', $response->json('capabilities', []), true);
        } catch (\Throwable $e) {
            Log::debug('Unable to detect thinking capability', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function extractTokensFromLastResponse()
    {
        // Cette méthode devrait être implémentée pour extraire les tokens de la dernière réponse
        // Pour l'instant, elle retourne 0
        return 0;
    }

    private function updateAssistantMessageInDatabase(Conversation $conversation, string $content)
    {
        // Cette méthode devrait être implémentée pour mettre à jour le message de l'assistant dans la base de données
        // Pour l'instant, elle ne fait rien
    }
}
