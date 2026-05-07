<?php

namespace App\Http\Controllers;

use App\Models\AIModel;
use App\Models\Collection as CollectionModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationMemoryService;
use App\Services\ModelSyncService;
use App\Services\OllamaHealthService;
use App\Services\QdrantCollectionsService;
use App\Services\RagService;
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

class ChatAppController extends Controller
{
    public function __construct(
        private readonly ConversationMemoryService $memoryService,
        private readonly RagService $ragService,
        private readonly QdrantCollectionsService $collectionsService,
        private readonly OllamaHealthService $ollamaHealth,
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

    public function selectModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
        ]);

        if (! collect($this->availableModels($this->ollamaHealth->isAvailable()))->pluck('name')->contains($validated['model'])) {
            throw ValidationException::withMessages([
                'model' => __('This model is not available for your account.'),
            ]);
        }

        session([
            'selected_model' => $validated['model'],
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

        if (! $validated['enabled']) {
            session()->forget('selected_collection');
        }

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
            'rag_enabled' => (bool) $collection,
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

        $this->collectionsService->deleteCollection($validated['collection']);
        CollectionModel::where('name', $validated['collection'])->delete();

        if (session('selected_collection') === $validated['collection']) {
            session()->forget('selected_collection');
            session(['rag_enabled' => false]);
        }

        return response()->json($this->statePayload());
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
        $fullPath = Storage::disk('public')->path($path);
        $mimeType = $file->getMimeType();

        if ($mimeType === 'application/pdf') {
            $content = $this->extractTextFromPdf($fullPath);
        } elseif (in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ], true)) {
            $content = $this->extractTextFromDocx($fullPath);
        } else {
            $content = Storage::disk('public')->get($path);
        }

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
        $this->ensureOllamaAvailable();

        $validated = $request->validate([
            'message' => ['required', 'string'],
            'model' => ['required', 'string'],
            'conversationId' => ['nullable', 'integer'],
            'ragEnabled' => ['boolean'],
            'selectedCollection' => ['nullable', 'string'],
            'temperature' => ['numeric', 'min:0', 'max:2'],
            'maxTokens' => ['integer', 'min:1', 'max:8192'],
        ]);

        if (! collect($this->availableModels())->pluck('name')->contains($validated['model'])) {
            throw ValidationException::withMessages([
                'model' => __('This model is not available for your account.'),
            ]);
        }

        $userMessage = trim($validated['message']);
        $temperature = (float) ($validated['temperature'] ?? session('temperature', 0.7));
        $maxTokens = (int) ($validated['maxTokens'] ?? session('max_tokens', 2048));
        $ragEnabled = (bool) ($validated['ragEnabled'] ?? session('rag_enabled', false));
        $selectedCollection = $validated['selectedCollection'] ?? session('selected_collection');

        session([
            'selected_model' => $validated['model'],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
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
            'settings' => [
                'model' => $validated['model'],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ],
        ]);

        $conversation->forceFill(['model_name' => $validated['model']])->save();
        $conversation->touch();

        $messages = $this->buildPromptMessages($conversation, $userMessage, $ragEnabled, $selectedCollection);

        return response()->json([
            'conversationId' => $conversation->id,
            'state' => $this->statePayload(),
            'streamPayload' => [
                'model' => $validated['model'],
                'messages' => $messages,
                'conversationId' => $conversation->id,
                'ragEnabled' => $ragEnabled,
                'selectedCollection' => $selectedCollection,
                'temperature' => $temperature,
                'maxTokens' => $maxTokens,
            ],
        ]);
    }

    public function summarize(Request $request): JsonResponse
    {
        $this->ensureOllamaAvailable();

        $validated = $request->validate([
            'conversationId' => ['required', 'integer'],
        ]);

        $conversation = Conversation::where('id', $validated['conversationId'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $this->memoryService->updateSummary($conversation->id);

        return response()->json($this->statePayload());
    }

    private function statePayload(): array
    {
        $ollamaStatus = $this->ollamaHealth->status();
        $models = $this->availableModels($ollamaStatus['available']);
        $selectedModel = session('selected_model', '');
        $modelNames = collect($models)->pluck('name');

        if (! $selectedModel || ! $modelNames->contains($selectedModel)) {
            $selectedModel = $models[0]['name'] ?? '';
            session(['selected_model' => $selectedModel]);
        }

        $conversations = Conversation::where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'model_name', 'tokens', 'updated_at']);

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

        return [
            'user' => [
                'name' => Auth::user()?->name,
                'isAdmin' => Auth::user()?->hasRole('admin') || Auth::user()?->hasRole('super-admin'),
            ],
            'locale' => app()->getLocale(),
            'ollama' => $ollamaStatus,
            'models' => $models,
            'selectedModel' => $selectedModel,
            'conversations' => $conversations->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'modelName' => $conversation->model_name,
                'tokens' => $conversation->tokens ?? 0,
                'updatedAt' => optional($conversation->updated_at)->toIso8601String(),
            ])->values(),
            'selectedConversationId' => $selectedConversationId,
            'messages' => $selectedConversationId ? $this->messagesForConversation($selectedConversationId) : [],
            'collections' => $collections,
            'selectedCollection' => $selectedCollection,
            'ragEnabled' => (bool) session('rag_enabled', false),
            'temperature' => (float) session('temperature', 0.7),
            'maxTokens' => (int) session('max_tokens', 2048),
            'tokenLimit' => $selectedModel && $ollamaStatus['available'] ? $this->tokenLimit($selectedModel) : null,
        ];
    }

    private function availableModels(bool $ollamaAvailable = true): array
    {
        $user = Auth::user();
        $userGroups = $user ? $user->groups->pluck('id')->toArray() : [];
        $isAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole('admin');

        if ($ollamaAvailable && ! AIModel::query()->where('is_active', true)->exists()) {
            app(ModelSyncService::class)->sync();
        }

        if ($user && ! $isAdmin && empty($userGroups)) {
            return [];
        }

        $query = AIModel::query()
            ->where('is_active', true)
            ->where('family', 'llm');

        if ($user && ! $isAdmin && ! empty($userGroups)) {
            $query->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $userGroups));
        }

        $embeddingModel = strtolower(config('services.ollama.embedding_model', 'nomic-embed-text'));

        return $query->orderBy('name')
            ->get(['full_name', 'size'])
            ->filter(function ($model) use ($embeddingModel) {
                $fullName = strtolower($model->full_name ?? '');

                return $fullName !== $embeddingModel
                    && ! str_contains($fullName, 'embed')
                    && ! str_contains($fullName, 'bomic-embed');
            })
            ->map(fn ($model) => [
                'name' => $model->full_name,
                'label' => explode(':', $model->full_name)[0],
                'size' => (int) ($model->size ?? 0),
            ])
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

    private function messagesForConversation(string $conversationId): array
    {
        return Message::whereHas('conversation', fn ($query) => $query->where('user_id', Auth::id()))
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn (Message $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
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

        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemPrompt,
        ]);

        return $messages;
    }

    private function tokenLimit(string $model): ?int
    {
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
            $parser = new \Smalot\PdfParser\Parser;

            return trim($parser->parseFile($filePath)->getText());
        } catch (\Throwable $e) {
            Log::error('Unable to extract PDF text', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'document' => __('Unable to extract text from this PDF.'),
            ]);
        }
    }

    private function extractTextFromDocx(string $filePath): string
    {
        if (! class_exists('\PhpOffice\PhpWord\IOFactory')) {
            throw ValidationException::withMessages([
                'document' => __('DOCX support is not installed.'),
            ]);
        }

        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
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
