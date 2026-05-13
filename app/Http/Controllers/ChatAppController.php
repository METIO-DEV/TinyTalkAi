<?php

namespace App\Http\Controllers;

use App\Models\AIModel;
use App\Models\Collection as CollectionModel;
use App\Models\Conversation;
use App\Models\ConversationDocument;
use App\Models\Message;
use App\Services\AIProviderAccountService;
use App\Services\AIProviderConfigService;
use App\Services\AIProviderModelSyncService;
use App\Services\ConversationMemoryService;
use App\Services\ModelSyncService;
use App\Services\OllamaHealthService;
use App\Services\QdrantCollectionsService;
use App\Services\RagService;
use App\Support\ChatMessageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class ChatAppController extends Controller
{
    private const STREAM_PAYLOAD_TTL_SECONDS = 300;

    private const RESPONSE_FORMAT_INSTRUCTIONS = 'Réponds en Markdown lisible. Quand tu fournis du code, utilise toujours des blocs de code fenced Markdown avec trois backticks et un libellé de langage précis, par exemple ```php, ```jsx ou ```bash. Ne mets pas de code multi-ligne en paragraphe.';

    public function __construct(
        private readonly ConversationMemoryService $memoryService,
        private readonly RagService $ragService,
        private readonly QdrantCollectionsService $collectionsService,
        private readonly OllamaHealthService $ollamaHealth,
        private readonly AIProviderAccountService $providerAccounts,
        private readonly AIProviderConfigService $providerConfigs,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Chat', [
            'initialState' => $this->statePayload(),
        ]);
    }

    public function state(): JsonResponse
    {
        return response()->json($this->statePayload());
    }

    public function saveOpenAIAccount(Request $request): JsonResponse
    {
        return $this->saveProviderAccount($request, 'openai');
    }

    public function saveProviderAccount(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            'apiKey' => ['nullable', 'string', 'min:20'],
            'organizationId' => ['nullable', 'string', 'max:255'],
            'projectId' => ['nullable', 'string', 'max:255'],
        ]);

        $this->validateAccountProvider($provider);
        $account = $this->providerAccounts->accountFor(Auth::user(), $provider);

        if (! $account && empty($validated['apiKey'])) {
            throw ValidationException::withMessages([
                'apiKey' => __(':provider API key is required.', [
                    'provider' => $this->providerConfigs->label($provider),
                ]),
            ]);
        }

        $this->providerAccounts->upsert(Auth::user(), $provider, [
            'api_key' => $validated['apiKey'] ?? null,
            'organization_id' => $validated['organizationId'] ?? null,
            'project_id' => $validated['projectId'] ?? null,
        ]);

        return response()->json($this->statePayload());
    }

    public function testOpenAIAccount(): JsonResponse
    {
        return $this->testProviderAccount('openai');
    }

    public function testProviderAccount(string $provider): JsonResponse
    {
        $this->validateAccountProvider($provider);
        $account = $this->providerAccounts->accountFor(Auth::user(), $provider);

        if (! $account) {
            throw ValidationException::withMessages([
                $provider => __('Connect your :provider account first.', [
                    'provider' => $this->providerConfigs->label($provider),
                ]),
            ]);
        }

        $result = $this->providerAccounts->test($account);
        $account->forceFill([
            'status' => $result['ok'] ? 'connected' : 'error',
            'last_verified_at' => now(),
            'last_error' => $result['ok'] ? null : $result['message'],
        ])->save();

        if ($result['ok']) {
            app(AIProviderModelSyncService::class)->sync($account);
        }

        return response()->json($this->statePayload());
    }

    public function disconnectOpenAIAccount(): JsonResponse
    {
        return $this->disconnectProviderAccount('openai');
    }

    public function disconnectProviderAccount(string $provider): JsonResponse
    {
        $this->validateAccountProvider($provider);
        $this->providerAccounts->disconnect(Auth::user(), $provider);

        if (session('selected_provider') === $provider) {
            session()->forget(['selected_provider', 'selected_model', 'selected_conversation_id']);
        }

        return response()->json($this->statePayload());
    }

    public function selectModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
            'provider' => ['nullable', 'string', 'in:'.implode(',', $this->providerConfigs->allowedModelProviders())],
        ]);

        $availableModel = collect($this->availableModels($this->ollamaHealth->isAvailable()))
            ->first(fn ($model) => $model['name'] === $validated['model']
                && (empty($validated['provider']) || $model['provider'] === $validated['provider']));

        if (! $availableModel) {
            throw ValidationException::withMessages([
                'model' => __('This model is not available for your account.'),
            ]);
        }

        session([
            'selected_model' => $validated['model'],
            'selected_provider' => $availableModel['provider'],
            'model_selection_source' => 'sidebar',
        ]);
        session()->forget('selected_conversation_id');

        return response()->json($this->statePayload());
    }

    public function selectConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversationId' => ['required', 'integer'],
        ]);

        $conversation = Conversation::where('id', $validated['conversationId'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        session([
            'selected_conversation_id' => $conversation->id,
            'selected_model' => $conversation->model_name,
            'selected_provider' => $conversation->provider ?? 'ollama',
        ]);

        return response()->json($this->statePayload());
    }

    public function newConversation(): JsonResponse
    {
        session()->forget('selected_conversation_id');

        return response()->json($this->statePayload());
    }

    public function deleteConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversationId' => ['required', 'integer'],
        ]);

        $conversation = Conversation::where('id', $validated['conversationId'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $conversation->delete();

        if ((int) session('selected_conversation_id') === (int) $validated['conversationId']) {
            session()->forget('selected_conversation_id');
        }

        return response()->json($this->statePayload());
    }

    public function toggleRag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        session(['rag_enabled' => $validated['enabled']]);

        return response()->json($this->statePayload());
    }

    public function selectCollection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'collection' => ['nullable', 'string'],
        ]);

        $collections = $this->availableCollections();
        $collection = $validated['collection'] ?? null;

        if ($collection && ! in_array($collection, $collections, true)) {
            throw ValidationException::withMessages([
                'collection' => __('This collection is not available for your account.'),
            ]);
        }

        session([
            'selected_collection' => $collection,
            'rag_enabled' => $collection ? true : (bool) session('rag_enabled', false),
        ]);

        return response()->json($this->statePayload());
    }

    public function createCollection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_]+$/'],
        ]);

        if (! $this->collectionsService->createCollection($validated['name'])) {
            throw ValidationException::withMessages([
                'name' => __('Error while creating the collection.'),
            ]);
        }

        CollectionModel::firstOrCreate(
            ['name' => $validated['name']],
            [
                'description' => 'Collection créée depuis l\'interface utilisateur',
                'is_active' => true,
                'metadata' => null,
                'user_id' => Auth::id(),
            ]
        );

        session([
            'selected_collection' => $validated['name'],
            'rag_enabled' => true,
        ]);

        return response()->json($this->statePayload());
    }

    public function deleteCollection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'collection' => ['required', 'string'],
        ]);

        if (! in_array($validated['collection'], $this->availableCollections(), true)) {
            throw ValidationException::withMessages([
                'collection' => __('This collection is not available for your account.'),
            ]);
        }

        $collection = CollectionModel::where('name', $validated['collection'])->firstOrFail();

        if (! $this->canManageCollection($collection)) {
            abort(403, __('Only the collection owner or an administrator can delete this collection.'));
        }

        if (! $this->collectionsService->deleteCollection($validated['collection'])) {
            throw ValidationException::withMessages([
                'collection' => __('Error while deleting the collection.'),
            ]);
        }

        $collection->delete();

        if (session('selected_collection') === $validated['collection']) {
            session()->forget('selected_collection');
            session(['rag_enabled' => false]);
        }

        return response()->json($this->statePayload());
    }

    public function uploadConversationDocument(Request $request): JsonResponse
    {
        $this->ensureOllamaAvailable();

        $validated = $request->validate([
            'conversationId' => ['nullable', 'integer'],
            'provider' => ['nullable', 'string', 'in:'.implode(',', $this->providerConfigs->allowedModelProviders())],
            'model' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:txt,pdf,docx', 'max:10240'],
        ]);

        $conversation = null;
        $conversationId = $validated['conversationId'] ?? session('selected_conversation_id');

        if ($conversationId) {
            $conversation = Conversation::where('id', $conversationId)
                ->where('user_id', Auth::id())
                ->first();
        }

        if (! $conversation) {
            $model = $validated['model'] ?? session('selected_model');
            $provider = $validated['provider'] ?? session('selected_provider', 'ollama');

            if (! $model) {
                throw ValidationException::withMessages([
                    'model' => __('Select a model before adding a document to a conversation.'),
                ]);
            }

            $conversation = Conversation::create([
                'user_id' => Auth::id(),
                'provider' => $provider,
                'title' => 'Document: '.$validated['title'],
                'model_name' => $model,
                'tokens' => 0,
            ]);
        }

        $file = $request->file('document');
        $documentId = (string) Str::uuid();
        $path = $file->store('documents', 'public');
        $content = $this->extractTextFromStoredDocument($path, $file->getMimeType());

        $metadata = [
            'title' => $validated['title'],
            'filename' => $file->getClientOriginalName(),
            'uploaded_by' => Auth::id(),
            'uploaded_at' => now()->toIso8601String(),
            'conversation_id' => $conversation->id,
            'scope' => 'conversation',
        ];

        $success = $this->ragService->processDocument($documentId, $content, $metadata);

        if (! $success) {
            throw ValidationException::withMessages([
                'document' => __('Error while processing the document.'),
            ]);
        }

        ConversationDocument::firstOrCreate([
            'conversation_id' => $conversation->id,
            'document_id' => $documentId,
        ]);

        $messageContent = 'Document ajouté à la conversation : '.$validated['title'];
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $messageContent,
            'content_parts' => ChatMessageContent::normalize($messageContent, [
                ['type' => 'text', 'text' => $messageContent],
                [
                    'type' => 'file',
                    'disk' => 'public',
                    'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'title' => $validated['title'],
                    'mediaType' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'fileId' => $documentId,
                ],
            ]),
            'settings' => [
                'rag_document_id' => $documentId,
                'rag_scope' => 'conversation',
            ],
        ]);

        $conversation->touch();

        session([
            'selected_conversation_id' => $conversation->id,
            'rag_enabled' => true,
            'selected_collection' => null,
        ]);

        return response()->json([
            ...$this->statePayload(),
            'uploadedDocument' => [
                'id' => $documentId,
                'title' => $validated['title'],
                'conversationId' => $conversation->id,
            ],
        ]);
    }

    public function uploadCollectionDocument(Request $request): JsonResponse
    {
        $this->ensureOllamaAvailable();

        $validated = $request->validate([
            'collection' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:txt,pdf,docx', 'max:10240'],
        ]);

        if (! in_array($validated['collection'], $this->availableCollections(), true)) {
            throw ValidationException::withMessages([
                'collection' => __('This collection is not available for your account.'),
            ]);
        }

        $file = $request->file('document');
        $documentId = (string) Str::uuid();
        $path = $file->store('documents', 'public');
        $content = $this->extractTextFromStoredDocument($path, $file->getMimeType());

        $metadata = [
            'title' => $validated['title'],
            'filename' => $file->getClientOriginalName(),
            'uploaded_by' => Auth::id(),
            'uploaded_at' => now()->toIso8601String(),
            'collection' => $validated['collection'],
        ];

        $success = $this->ragService->processDocument(
            $documentId,
            $content,
            $metadata,
            $validated['collection']
        );

        if (! $success) {
            throw ValidationException::withMessages([
                'document' => __('Error while processing the document.'),
            ]);
        }

        session([
            'selected_collection' => $validated['collection'],
            'rag_enabled' => true,
        ]);

        return response()->json([
            ...$this->statePayload(),
            'uploadedDocument' => [
                'id' => $documentId,
                'title' => $validated['title'],
                'collection' => $validated['collection'],
            ],
        ]);
    }

    public function prepareMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
            'model' => ['required', 'string'],
            'provider' => ['nullable', 'string', 'in:'.implode(',', $this->providerConfigs->allowedModelProviders())],
            'conversationId' => ['nullable', 'integer'],
            'ragEnabled' => ['boolean'],
            'selectedCollection' => ['nullable', 'string'],
            'temperature' => ['numeric', 'min:0', 'max:2'],
            'maxTokens' => ['integer', 'min:1', 'max:8192'],
            'reasoningEffort' => ['nullable', 'string', 'in:none,low,medium,high,xhigh'],
        ]);

        $availableModel = collect($this->availableModels())
            ->first(fn ($model) => $model['name'] === $validated['model']
                && (empty($validated['provider']) || $model['provider'] === $validated['provider']));

        if (! $availableModel) {
            throw ValidationException::withMessages([
                'model' => __('This model is not available for your account.'),
            ]);
        }

        $provider = $availableModel['provider'];
        $this->ensureProviderAvailable($provider);

        $userMessage = trim($validated['message']);
        $temperature = (float) ($validated['temperature'] ?? session('temperature', 0.7));
        $maxTokens = (int) ($validated['maxTokens'] ?? session('max_tokens', 2048));
        $reasoningEffort = $validated['reasoningEffort'] ?? session('reasoning_effort', 'medium');
        $ragEnabled = (bool) ($validated['ragEnabled'] ?? session('rag_enabled', false));
        $selectedCollection = $validated['selectedCollection'] ?? session('selected_collection');

        session([
            'selected_model' => $validated['model'],
            'selected_provider' => $provider,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'reasoning_effort' => $reasoningEffort,
            'rag_enabled' => $ragEnabled,
            'selected_collection' => $selectedCollection,
        ]);

        $conversation = null;

        if (! empty($validated['conversationId'])) {
            $conversation = Conversation::where('id', $validated['conversationId'])
                ->where('user_id', Auth::id())
                ->first();
        }

        if (! $conversation) {
            $conversation = Conversation::create([
                'user_id' => Auth::id(),
                'provider' => $provider,
                'title' => Str::limit($userMessage, 50, '...'),
                'model_name' => $validated['model'],
                'tokens' => 0,
            ]);
        }

        session(['selected_conversation_id' => $conversation->id]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'content_parts' => ChatMessageContent::fromText($userMessage),
            'settings' => [
                'provider' => $provider,
                'model' => $validated['model'],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'reasoning_effort' => $reasoningEffort,
            ],
        ]);

        $conversation->forceFill([
            'provider' => $provider,
            'model_name' => $validated['model'],
        ])->save();
        $conversation->touch();

        $messages = $this->buildPromptMessages($conversation, $userMessage, $ragEnabled, $selectedCollection);
        $tokenLimit = $this->tokenLimit($validated['model'], $provider);
        $streamToken = Str::random(40);
        $streamPayload = [
            'provider' => $provider,
            'model' => $validated['model'],
            'messages' => $messages,
            'conversationId' => $conversation->id,
            'ragEnabled' => $ragEnabled,
            'selectedCollection' => $selectedCollection,
            'temperature' => $temperature,
            'maxTokens' => $maxTokens,
            'reasoningEffort' => $reasoningEffort,
            'tokenLimit' => $tokenLimit,
        ];

        $payloads = $this->activeStreamPayloads();
        $payloads[$streamToken] = [
            'expires_at' => now()->addSeconds(self::STREAM_PAYLOAD_TTL_SECONDS)->timestamp,
            'payload' => $streamPayload,
        ];

        session(['chat_stream_payloads' => $payloads]);

        return response()->json([
            'conversationId' => $conversation->id,
            'state' => $this->statePayload(),
            'streamPayload' => [
                'streamToken' => $streamToken,
            ],
        ]);
    }

    private function statePayload(): array
    {
        $ollamaStatus = $this->ollamaHealth->status();
        $models = $this->availableModels($ollamaStatus['available']);
        $selectedModel = session('selected_model', '');
        $selectedProvider = session('selected_provider', '');
        $selectedModelRecord = collect($models)->first(fn ($model) => $model['name'] === $selectedModel
            && (! $selectedProvider || $model['provider'] === $selectedProvider));

        if (! $selectedModel || ! $selectedModelRecord) {
            $selectedModelRecord = $models[0] ?? null;
            $selectedModel = $selectedModelRecord['name'] ?? '';
            $selectedProvider = $selectedModelRecord['provider'] ?? '';
            session(['selected_model' => $selectedModel]);
            session(['selected_provider' => $selectedProvider]);
        }

        $conversations = Conversation::where('user_id', Auth::id())
            ->withCount('documents')
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'provider', 'model_name', 'tokens', 'updated_at']);

        $selectedConversationId = session('selected_conversation_id');
        if ($selectedConversationId !== null) {
            $selectedConversationId = (int) $selectedConversationId;
        }

        $selectedConversation = $selectedConversationId
            ? $conversations->firstWhere('id', $selectedConversationId)
            : null;

        if ($selectedConversationId && ! $selectedConversation) {
            session()->forget('selected_conversation_id');
            $selectedConversationId = null;
        }

        $collections = $this->availableCollections();
        $selectedCollection = session('selected_collection');
        if ($selectedCollection && ! in_array($selectedCollection, $collections, true)) {
            session()->forget('selected_collection');
            $selectedCollection = null;
        }

        $providerPayloads = $this->providerAccounts->accountPayloads(Auth::user());

        return [
            'user' => [
                'name' => Auth::user()?->name,
                'isAdmin' => Auth::user()?->hasRole('admin') || Auth::user()?->hasRole('super-admin'),
            ],
            'locale' => app()->getLocale(),
            'ollama' => $ollamaStatus,
            'providers' => $providerPayloads,
            'openai' => $providerPayloads['openai'] ?? $this->disconnectedProviderPayload('openai'),
            'models' => $models,
            'selectedModel' => $selectedModel,
            'selectedProvider' => $selectedProvider,
            'conversations' => $conversations->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'provider' => $conversation->provider ?? 'ollama',
                'modelName' => $conversation->model_name,
                'tokens' => $conversation->tokens ?? 0,
                'documentCount' => $conversation->documents_count ?? 0,
                'updatedAt' => optional($conversation->updated_at)->toIso8601String(),
            ])->values(),
            'selectedConversationId' => $selectedConversationId,
            'messages' => $selectedConversationId ? $this->messagesForConversation($selectedConversationId) : [],
            'selectedConversationDocumentCount' => $selectedConversationId
                ? (int) ConversationDocument::whereHas(
                    'conversation',
                    fn ($query) => $query->where('user_id', Auth::id())
                )->where('conversation_id', $selectedConversationId)->count()
                : 0,
            'selectedConversationDocumentTitles' => $selectedConversationId
                ? $this->conversationDocumentTitles($selectedConversationId)
                : [],
            'collections' => $collections,
            'collectionStats' => $this->collectionStats($collections),
            'deletableCollections' => $this->deletableCollections($collections),
            'selectedCollection' => $selectedCollection,
            'ragEnabled' => (bool) session('rag_enabled', false),
            'temperature' => (float) session('temperature', 0.7),
            'maxTokens' => (int) session('max_tokens', 2048),
            'reasoningEffort' => session('reasoning_effort', 'medium'),
            'tokenLimit' => $selectedModel ? $this->tokenLimit($selectedModel, $selectedProvider ?: 'ollama') : null,
        ];
    }

    private function activeStreamPayloads(): array
    {
        $now = now()->timestamp;
        $payloads = [];

        foreach (session('chat_stream_payloads', []) as $token => $entry) {
            if (! is_string($token) || ! is_array($entry)) {
                continue;
            }

            if (! isset($entry['expires_at'], $entry['payload']) || ! is_array($entry['payload'])) {
                continue;
            }

            if ((int) $entry['expires_at'] <= $now) {
                continue;
            }

            $payloads[$token] = $entry;
        }

        return array_slice($payloads, -20, null, true);
    }

    private function availableModels(bool $ollamaAvailable = true): array
    {
        $user = Auth::user();
        $userGroups = $user ? $user->groups->pluck('id')->toArray() : [];
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');
        $models = collect();

        if ($ollamaAvailable && ! AIModel::query()->where('provider', 'ollama')->where('is_active', true)->exists()) {
            app(ModelSyncService::class)->sync();
        }

        if (! $user || $isAdmin || ! empty($userGroups)) {
            $query = AIModel::query()
                ->where('provider', 'ollama')
                ->where('is_active', true)
                ->where('family', 'llm');

            if ($user && ! $isAdmin && ! empty($userGroups)) {
                $query->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $userGroups));
            }

            $embeddingModel = strtolower(config('services.ollama.embedding_model', 'nomic-embed-text'));

            $models = $models->merge($query->orderBy('name')
                ->get(['provider', 'full_name', 'name', 'size', 'context_window', 'max_output_tokens', 'capabilities'])
                ->filter(function ($model) use ($embeddingModel) {
                    $fullName = strtolower($model->full_name ?? '');

                    return $fullName !== $embeddingModel
                        && ! str_contains($fullName, 'embed')
                        && ! str_contains($fullName, 'bomic-embed');
                })
                ->map(fn ($model) => [
                    'provider' => 'ollama',
                    'name' => $model->full_name,
                    'label' => explode(':', $model->full_name)[0],
                    'size' => (int) ($model->size ?? 0),
                    'contextWindow' => $model->context_window,
                    'maxOutputTokens' => $model->max_output_tokens,
                    'capabilities' => $model->capabilities ?? [],
                ]));
        }

        foreach ($this->providerConfigs->ids() as $provider) {
            $account = $user ? $this->providerAccounts->accountFor($user, $provider) : null;

            if ($account?->status !== 'connected') {
                continue;
            }

            $models = $models->merge(AIModel::query()
                ->where('provider', $provider)
                ->where('is_active', true)
                ->where('family', 'llm')
                ->orderBy('name')
                ->get(['provider', 'full_name', 'name', 'size', 'context_window', 'max_output_tokens', 'capabilities'])
                ->map(fn ($model) => [
                    'provider' => $provider,
                    'name' => $model->full_name,
                    'label' => $model->name,
                    'size' => null,
                    'contextWindow' => $model->context_window,
                    'maxOutputTokens' => $model->max_output_tokens,
                    'capabilities' => $model->capabilities ?? [],
                ]));
        }

        return $models
            ->values()
            ->toArray();
    }

    private function availableCollections(): array
    {
        $user = Auth::user();
        $defaultCollection = config('services.qdrant.collection', 'docs');

        if (! $user) {
            return [];
        }

        $groupCollections = $user->groups()->exists()
            ? CollectionModel::whereHas('groups', fn ($query) => $query->whereIn('groups.id', $user->groups->pluck('id')))
                ->where('is_active', true)
                ->where('name', '!=', $defaultCollection)
                ->pluck('name')
                ->toArray()
            : [];

        $ownedCollections = CollectionModel::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('name', '!=', $defaultCollection)
            ->pluck('name')
            ->toArray();

        $collections = array_values(array_unique(array_merge($groupCollections, $ownedCollections)));
        sort($collections);

        return $collections;
    }

    private function deletableCollections(array $availableCollections): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        if ($this->isAdminUser()) {
            return $availableCollections;
        }

        return CollectionModel::where('user_id', $user->id)
            ->whereIn('name', $availableCollections)
            ->pluck('name')
            ->toArray();
    }

    private function collectionStats(array $collections): array
    {
        $stats = [];

        foreach ($collections as $collection) {
            $stats[$collection] = $this->collectionsService->getDocumentStats($collection);
        }

        return $stats;
    }

    private function conversationDocumentTitles(int $conversationId): array
    {
        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', Auth::id())
            ->with('documents')
            ->first();

        if (! $conversation) {
            return [];
        }

        $titles = [];

        Message::where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->orderByDesc('created_at')
            ->get(['content', 'content_parts'])
            ->each(function (Message $message) use (&$titles) {
                foreach (ChatMessageContent::normalize($message->content, $message->content_parts) as $part) {
                    if (($part['type'] ?? null) !== 'file') {
                        continue;
                    }

                    $title = $part['title'] ?? $part['filename'] ?? $part['fileId'] ?? null;

                    if (is_string($title) && $title !== '') {
                        $titles[] = $title;
                    }

                    if (count($titles) >= 3) {
                        return false;
                    }
                }

                return null;
            });

        if (count($titles) < 3) {
            $fallbackIds = $conversation->documents()
                ->latest()
                ->pluck('document_id')
                ->take(3 - count($titles))
                ->toArray();

            $titles = array_merge($titles, $fallbackIds);
        }

        return array_values(array_slice(array_unique($titles), 0, 3));
    }

    private function canManageCollection(CollectionModel $collection): bool
    {
        return $this->isAdminUser() || (int) $collection->user_id === (int) Auth::id();
    }

    private function isAdminUser(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->hasRole('admin') || $user?->hasRole('super-admin'));
    }

    private function messagesForConversation(string $conversationId): array
    {
        return Message::whereHas('conversation', fn ($query) => $query->where('user_id', Auth::id()))
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get(['role', 'content', 'content_parts'])
            ->map(fn (Message $message) => ChatMessageContent::toClientMessage(
                $message->role,
                $message->content,
                $message->content_parts,
            ))
            ->toArray();
    }

    private function buildPromptMessages(
        Conversation $conversation,
        string $userMessage,
        bool $ragEnabled,
        ?string $selectedCollection,
    ): array {
        $messages = [];

        if ($conversation->summary_flag && $conversation->summary) {
            $messages[] = [
                'role' => 'system',
                'content' => "Résumé de la conversation précédente: {$conversation->summary}",
            ];
        }

        $messages = array_merge($messages, $this->memoryService->getLocalWindow($conversation->id));

        $systemPrompt = "Aucun document pertinent n'a été trouvé dans la base de connaissances pour cette question. N'indique pas que tu réponds selon tes connaissances générales. Réponds au mieux à la question posée.";
        $contextDocuments = [];

        if ($ragEnabled) {
            if ($selectedCollection) {
                $contextDocuments = $this->ragService->searchSimilarDocuments($userMessage, 4, null, $selectedCollection);
            } else {
                $documentIds = $conversation->getDocumentIds();

                if ($documentIds) {
                    $contextDocuments = $this->ragService->searchSimilarDocuments($userMessage, 4, $documentIds);
                } else {
                    $systemPrompt = "<<<SYSTEM\nAucun document n'est actuellement associé à cette conversation alors que le mode RAG est activé. Informe l'utilisateur qu'il doit joindre un document, sélectionner une collection documentaire ou désactiver le mode RAG.\nSYSTEM";
                }
            }
        }

        if ($contextDocuments) {
            $systemPrompt = "Voici des extraits de documents pertinents pour répondre à la question:\n\n";

            foreach ($contextDocuments as $index => $document) {
                $systemPrompt .= 'Contexte '.($index + 1).":\n".$document['text']."\n\n";
            }

            $systemPrompt .= "Utilise ces informations pour enrichir ta réponse à la question de l'utilisateur.";
        }

        $systemPrompt = self::RESPONSE_FORMAT_INSTRUCTIONS."\n\n".$systemPrompt;

        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemPrompt,
        ]);

        return $messages;
    }

    private function tokenLimit(string $model, string $provider = 'ollama'): ?int
    {
        if ($provider !== 'ollama') {
            return AIModel::query()
                ->where('provider', $provider)
                ->where('full_name', $model)
                ->value('context_window');
        }

        try {
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434');
            $response = Http::timeout(2)->post("http://{$ollamaHost}:{$ollamaPort}/api/show", [
                'name' => $model,
            ]);

            if (! $response->successful()) {
                return null;
            }

            return $this->findContextLength($response->json('model_info', []));
        } catch (\Throwable $e) {
            Log::debug('Unable to fetch token limit', ['model' => $model, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function ensureOllamaAvailable(): void
    {
        $status = $this->ollamaHealth->status();

        if (! $status['available']) {
            throw ValidationException::withMessages([
                'ollama' => $status['message'],
            ]);
        }
    }

    private function ensureProviderAvailable(string $provider): void
    {
        if ($provider !== 'ollama') {
            $this->validateAccountProvider($provider);
            $account = $this->providerAccounts->accountFor(Auth::user(), $provider);

            if (! $account || $account->status !== 'connected') {
                throw ValidationException::withMessages([
                    $provider => __('Connect your :provider account before using its models.', [
                        'provider' => $this->providerConfigs->label($provider),
                    ]),
                ]);
            }

            return;
        }

        $this->ensureOllamaAvailable();
    }

    private function validateAccountProvider(string $provider): void
    {
        if (! in_array($provider, $this->providerConfigs->ids(), true)) {
            abort(404);
        }
    }

    private function disconnectedProviderPayload(string $provider): array
    {
        return [
            'id' => $provider,
            'label' => $this->providerConfigs->label($provider),
            'connected' => false,
            'status' => 'disconnected',
            'keyPreview' => null,
            'organizationId' => null,
            'projectId' => null,
            'lastVerifiedAt' => null,
            'lastError' => null,
            'supportsOrganizationId' => $provider === 'openai',
            'supportsProjectId' => $provider === 'openai',
        ];
    }

    private function findContextLength(mixed $data): ?int
    {
        if (! is_array($data)) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && stripos($key, 'context_length') !== false && is_numeric($value)) {
                return (int) $value;
            }

            if (is_array($value)) {
                $result = $this->findContextLength($value);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    private function extractTextFromPdf(string $filePath): string
    {
        if (! class_exists('\Smalot\PdfParser\Parser')) {
            throw ValidationException::withMessages([
                'document' => __('PDF support is not installed.'),
            ]);
        }

        try {
            $parser = new Parser;

            return trim($parser->parseFile($filePath)->getText());
        } catch (\Throwable $e) {
            Log::error('Unable to extract PDF text', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'document' => __('Unable to extract text from this PDF.'),
            ]);
        }
    }

    private function extractTextFromStoredDocument(string $path, ?string $mimeType): string
    {
        $fullPath = Storage::disk('public')->path($path);

        if ($mimeType === 'application/pdf') {
            return $this->extractTextFromPdf($fullPath);
        }

        if (in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ], true)) {
            return $this->extractTextFromDocx($fullPath);
        }

        return Storage::disk('public')->get($path);
    }

    private function extractTextFromDocx(string $filePath): string
    {
        if (! class_exists('\PhpOffice\PhpWord\IOFactory')) {
            throw ValidationException::withMessages([
                'document' => __('DOCX support is not installed.'),
            ]);
        }

        try {
            $phpWord = IOFactory::load($filePath);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText().' ';
                    }
                }
            }

            return trim($text);
        } catch (\Throwable $e) {
            Log::error('Unable to extract DOCX text', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'document' => __('Unable to extract text from this DOCX.'),
            ]);
        }
    }
}
