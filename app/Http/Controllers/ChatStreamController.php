<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\UserAIProviderAccount;
use App\Services\AIProviderConfigService;
use App\Services\AIProviderStreamService;
use App\Services\ConversationMemoryService;
use App\Services\OllamaHealthService;
use App\Services\RagService;
use App\Support\ChatMessageContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatStreamController extends Controller
{
    protected ConversationMemoryService $memoryService;

    protected RagService $ragService;

    protected OllamaHealthService $ollamaHealth;

    public function __construct(
        ConversationMemoryService $memoryService,
        RagService $ragService,
        OllamaHealthService $ollamaHealth,
        private readonly AIProviderStreamService $providerStream,
        private readonly AIProviderConfigService $providerConfigs,
    ) {
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

        $validated = $request->validate([
            'streamToken' => 'required|string',
        ]);

        $payloads = session('chat_stream_payloads', []);
        $streamEntry = $payloads[$validated['streamToken']] ?? null;
        unset($payloads[$validated['streamToken']]);
        session(['chat_stream_payloads' => $payloads]);

        $streamPayload = is_array($streamEntry) && (int) ($streamEntry['expires_at'] ?? 0) > now()->timestamp
            ? ($streamEntry['payload'] ?? null)
            : null;

        if (! is_array($streamPayload)) {
            throw ValidationException::withMessages([
                'streamToken' => __('This chat stream is no longer available. Please send the message again.'),
            ]);
        }

        if (! empty($streamPayload['conversationId'])) {
            Conversation::where('id', $streamPayload['conversationId'])
                ->where('user_id', Auth::id())
                ->firstOrFail();
        }

        $provider = $streamPayload['provider'] ?? 'ollama';

        if ($provider === 'ollama') {
            $ollamaStatus = $this->ollamaHealth->status();
            if (! $ollamaStatus['available']) {
                return response()->json(['message' => $ollamaStatus['message']], 503);
            }
        }

        if ($provider !== 'ollama') {
            $account = UserAIProviderAccount::query()
                ->where('user_id', Auth::id())
                ->where('provider', $provider)
                ->where('status', 'connected')
                ->first();

            if (! $account) {
                return response()->json([
                    'message' => __('Connect your :provider account before using its models.', [
                        'provider' => $this->providerConfigs->label($provider),
                    ]),
                ], 422);
            }
        }

        return new StreamedResponse(function () use ($streamPayload) {
            // Configuration des headers SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Pour nginx

            try {
                if (($streamPayload['provider'] ?? 'ollama') !== 'ollama') {
                    $this->initializeStreamState($streamPayload);

                    $account = UserAIProviderAccount::query()
                        ->where('user_id', Auth::id())
                        ->where('provider', $streamPayload['provider'])
                        ->where('status', 'connected')
                        ->firstOrFail();

                    $this->providerStream->stream(
                        $account,
                        $streamPayload,
                        fn (string $content) => $this->appendAndSendChunk($content),
                        fn (array $event) => $this->handleFinalProviderResponse($event),
                    );

                    $this->sendSSEEvent('complete', [
                        'response' => $this->completeResponse,
                        'message' => $this->finalMessage ?? ChatMessageContent::toClientMessage(
                            'assistant',
                            $this->completeResponse,
                            ChatMessageContent::fromText($this->completeResponse),
                        ),
                        'conversationId' => $this->conversationId,
                        'tokens' => $this->lastResponseTokens,
                    ]);

                    $this->sendSSEEvent('close', []);
                    $this->summarizeConversationIfNeeded();

                    return;
                }

                // Préparer les données pour l'API Ollama
                $ollamaHost = config('services.ollama.host', 'localhost');
                $ollamaPort = config('services.ollama.port', '11434');
                $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/chat';
                $thinkingEnabled = $this->modelSupportsThinking($streamPayload['model']);

                $payload = [
                    'model' => $streamPayload['model'],
                    'messages' => $streamPayload['messages'],
                    'options' => [
                        'temperature' => (float) ($streamPayload['temperature'] ?? 0.7),
                        'num_predict' => (int) ($streamPayload['maxTokens'] ?? 2048),
                    ],
                    'stream' => true,
                ];

                if ($thinkingEnabled) {
                    $payload['think'] = true;
                }

                Log::info('Démarrage du streaming vers Ollama', [
                    'url' => $ollamaUrl,
                    'model' => $streamPayload['model'],
                    'message_count' => count($streamPayload['messages']),
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
                $this->initializeStreamState($streamPayload);

                // Exécuter la requête cURL
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($result === false || ! empty($error)) {
                    if (connection_aborted() || str_contains(strtolower($error), 'write')) {
                        Log::info('Streaming arrêté par le client', [
                            'model' => $streamPayload['model'],
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
                        'message' => $this->finalMessage ?? ChatMessageContent::toClientMessage(
                            'assistant',
                            $this->completeResponse,
                            ChatMessageContent::fromText($this->completeResponse),
                        ),
                        'conversationId' => $this->conversationId,
                        'tokens' => $this->lastResponseTokens,
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
            $this->summarizeConversationIfNeeded();
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

    private ?array $finalMessage = null;

    private int $lastResponseTokens = 0;

    private string $provider = 'ollama';

    private ?int $tokenLimit = null;

    private ?int $autoSummaryConversationId = null;

    private function initializeStreamState(array $streamPayload): void
    {
        $this->completeResponse = '';
        $this->conversationId = isset($streamPayload['conversationId'])
            ? (int) $streamPayload['conversationId']
            : null;
        $this->provider = $streamPayload['provider'] ?? 'ollama';
        $this->model = $streamPayload['model'];
        $this->temperature = $streamPayload['temperature'] ?? 0.7;
        $this->maxTokens = $streamPayload['maxTokens'] ?? 2048;
        $this->ragEnabled = $streamPayload['ragEnabled'] ?? false;
        $this->thinkingOpen = false;
        $this->finalMessage = null;
        $this->lastResponseTokens = 0;
        $this->tokenLimit = isset($streamPayload['tokenLimit']) ? (int) $streamPayload['tokenLimit'] : null;
        $this->autoSummaryConversationId = null;
    }

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
            $assistantParts = $this->buildAssistantContentParts($json);
            $assistantContent = ChatMessageContent::toPlainText($assistantParts, $this->completeResponse);
            $this->lastResponseTokens = $totalTokens;

            Log::info('Réponse streaming complète', [
                'response_length' => strlen($assistantContent),
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
                    $message = Message::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'assistant',
                        'content' => $assistantContent,
                        'content_parts' => $assistantParts,
                        'settings' => [
                            'model' => $this->model,
                            'temperature' => $this->temperature,
                            'max_tokens' => $this->maxTokens,
                            'tokens_used' => $totalTokens,
                            'rag_enabled' => $this->ragEnabled,
                            'streaming' => true,
                            'provider' => $this->provider,
                        ],
                    ]);

                    $this->finalMessage = ChatMessageContent::toClientMessage(
                        $message->role,
                        $message->content,
                        $message->content_parts,
                    );

                    Log::info('Message assistant sauvegardé', [
                        'conversation_id' => $conversation->id,
                        'tokens' => $totalTokens,
                    ]);

                    $this->autoSummaryConversationId = $conversation->id;
                }
            }

        } catch (\Exception $e) {
            Log::error('Erreur lors de la sauvegarde de la réponse finale', [
                'error' => $e->getMessage(),
                'conversation_id' => $this->conversationId,
            ]);
        }
    }

    private function handleFinalProviderResponse(array $event): void
    {
        $response = $event['response'] ?? [];
        $usage = $response['usage'] ?? $event['message']['usage'] ?? $event['usage'] ?? [];
        $inputTokens = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
        $outputTokens = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
        $totalTokens = (int) ($usage['total_tokens'] ?? ($inputTokens + $outputTokens));
        $this->lastResponseTokens = $totalTokens;

        if (! Auth::check() || ! $this->conversationId || $this->finalMessage) {
            return;
        }

        $conversation = Conversation::where('id', $this->conversationId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $conversation) {
            return;
        }

        if ($totalTokens > 0) {
            $conversation->increment('tokens', $totalTokens);
        }

        $assistantParts = $this->buildAssistantContentParts($response);
        $assistantContent = ChatMessageContent::toPlainText($assistantParts, $this->completeResponse);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $assistantContent,
            'content_parts' => $assistantParts,
            'settings' => [
                'provider' => $this->provider,
                'model' => $this->model,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
                'tokens_used' => $totalTokens,
                'rag_enabled' => $this->ragEnabled,
                'streaming' => true,
            ],
        ]);

        $this->finalMessage = ChatMessageContent::toClientMessage(
            $message->role,
            $message->content,
            $message->content_parts,
        );

        $this->autoSummaryConversationId = $conversation->id;
    }

    private function summarizeConversationIfNeeded(): void
    {
        $tokenLimit = $this->tokenLimit;

        if (! $this->autoSummaryConversationId || ! $tokenLimit || $tokenLimit <= 0) {
            return;
        }

        $conversation = Conversation::where('id', $this->autoSummaryConversationId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $conversation) {
            return;
        }

        if (! $this->memoryService->shouldSummarize($conversation->id, (int) $conversation->tokens, $tokenLimit)) {
            return;
        }

        $this->memoryService->updateSummary($conversation->id);
    }

    private function buildAssistantContentParts(array $payload): array
    {
        $messageContent = $payload['message']['content'] ?? null;

        if (is_array($messageContent)) {
            return ChatMessageContent::normalize($messageContent);
        }

        if (is_array($payload['output'] ?? null)) {
            return ChatMessageContent::normalize($payload['output']);
        }

        return ChatMessageContent::fromText($this->completeResponse);
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

    private function updateAssistantMessageInDatabase(Conversation $conversation, string $content)
    {
        // Cette méthode devrait être implémentée pour mettre à jour le message de l'assistant dans la base de données
        // Pour l'instant, elle ne fait rien
    }
}
