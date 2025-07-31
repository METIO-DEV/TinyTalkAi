<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\ConversationDocument;
use App\Services\RagService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class DocumentUploader extends Component
{
    use WithFileUploads;

    /**
     * Le fichier à uploader
     */
    public $document;

    /**
     * Le titre du document
     */
    public string $title = '';

    /**
     * Le modèle d'embedding à utiliser
     */
    public string $embeddingModel = '';

    /**
     * ID de la conversation courante
     */
    public ?string $conversationId = null;

    /**
     * Indicateur de chargement pendant le traitement
     */
    public bool $isProcessing = false;

    /**
     * Indique si le modal d'upload est ouvert
     */
    public bool $isOpen = false;

    /**
     * Message de statut
     */
    public string $statusMessage = '';

    /**
     * Indique si l'opération a réussi
     */
    public bool $success = false;

    /**
     * Service RAG
     */
    protected RagService $ragService;

    /**
     * Constructeur du composant
     */
    public function boot(RagService $ragService)
    {
        $this->ragService = $ragService;
    }

    /**
     * Règles de validation
     */
    protected function rules()
    {
        return [
            'document' => 'required|file|mimes:txt|max:10240', // 10MB max
            'title' => 'required|string|max:255',
        ];
    }

    /**
     * Messages de validation
     */
    protected function messages()
    {
        return [
            'document.required' => 'Veuillez sélectionner un fichier.',
            'document.file' => 'Le fichier est invalide.',
            'document.mimes' => 'Seuls les fichiers .txt sont acceptés.',
            'document.max' => 'La taille du fichier ne doit pas dépasser 10MB.',
            'title.required' => 'Le titre du document est requis.',
            'title.max' => 'Le titre ne doit pas dépasser 255 caractères.',
        ];
    }

    /**
     * Initialisation du composant
     */
    public function mount()
    {
        // Récupérer le modèle d'embedding depuis la configuration
        $this->embeddingModel = config('services.ollama.embedding_model', 'nomic-embed-text:latest');

        // Récupérer l'ID de la conversation courante depuis la session
        $this->conversationId = session('selected_conversation_id');

        // Log pour déboguer
        Log::info('DocumentUploader: initialisation avec conversation_id', [
            'session_conversation_id' => session('selected_conversation_id'),
            'component_conversation_id' => $this->conversationId,
        ]);
    }

    /**
     * Écouteurs d'événements
     */
    protected $listeners = [
        'openDocumentUploader' => 'openModal',
        'conversationSelected' => 'updateConversationId',
        'closeModalAfterDelay' => 'closeModalAfterDelay',
    ];

    /**
     * Met à jour l'ID de la conversation courante
     */
    public function updateConversationId(string $conversationId)
    {
        $this->conversationId = $conversationId;
    }

    /**
     * Ouvre le modal d'upload
     */
    public function openModal()
    {
        // Vérifier à nouveau l'ID de conversation au moment d'ouvrir le modal
        $this->conversationId = session('selected_conversation_id');

        Log::info('DocumentUploader: ouverture du modal avec conversation_id', [
            'conversation_id' => $this->conversationId,
        ]);

        $this->isOpen = true;
        $this->resetForm();
    }

    /**
     * Ferme le modal d'upload
     */
    public function closeModal()
    {
        $this->isOpen = false;
        $this->resetForm();
    }

    /**
     * Ferme le modal d'upload après un délai
     */
    public function closeModalAfterDelay()
    {
        // délais 1 seconde
        sleep(1);
        $this->isOpen = false;
        $this->resetForm();
    }

    /**
     * Réinitialise le formulaire
     */
    public function resetForm()
    {
        $this->document = null;
        $this->title = '';
        $this->statusMessage = '';
        $this->isProcessing = false;
        $this->success = false;
        $this->resetValidation();
    }

    /**
     * Upload et traitement du document
     */
    public function uploadDocument()
    {
        Log::info('DocumentUploader: début de la méthode uploadDocument');

        try {
            $this->validate();

            // Vérifier à nouveau l'ID de conversation au moment de l'upload
            $this->conversationId = session('selected_conversation_id');

            Log::info('DocumentUploader: validation passée avec succès', [
                'title' => $this->title,
                'document' => $this->document ? 'présent' : 'absent',
                'conversation_id' => $this->conversationId,
            ]);

            $this->isProcessing = true;
            $this->statusMessage = 'Traitement en cours...';
            $this->success = false;

            // Générer un ID unique pour le document
            $documentId = (string) Str::uuid();

            // Stocker le fichier
            $path = $this->document->store('documents', 'public');
            Log::info('DocumentUploader: fichier stocké', ['path' => $path]);

            // Lire le contenu du fichier
            $content = Storage::disk('public')->get($path);

            // Métadonnées du document
            $metadata = [
                'title' => $this->title,
                'filename' => $this->document->getClientOriginalName(),
                'uploaded_by' => Auth::id() ?? 'guest',
                'uploaded_at' => now()->toIso8601String(),
            ];

            // Définir le modèle d'embedding à utiliser
            $this->ragService->setEmbeddingModel($this->embeddingModel);

            // Traiter le document avec le service RAG
            Log::info('DocumentUploader: appel à processDocument', [
                'documentId' => $documentId,
                'contentLength' => strlen($content),
                'metadata' => $metadata,
            ]);

            $success = $this->ragService->processDocument($documentId, $content, $metadata);

            if ($success) {
                Log::info('DocumentUploader: document traité avec succès');

                // Si aucune conversation n'est sélectionnée et que l'utilisateur est connecté, en créer une nouvelle
                if (! $this->conversationId && Auth::check()) {
                    try {
                        // Créer une nouvelle conversation avec le titre du document
                        $conversation = Conversation::create([
                            'user_id' => Auth::id(),
                            'title' => 'Document: '.$this->title,
                            'model_name' => session('selected_model'),
                            'tokens' => 0,
                        ]);

                        $this->conversationId = $conversation->id;
                        session(['selected_conversation_id' => $this->conversationId]);

                        Log::info('DocumentUploader: nouvelle conversation créée pour le document', [
                            'conversation_id' => $this->conversationId,
                            'document_title' => $this->title,
                        ]);

                        // Informer les autres composants qu'une nouvelle conversation a été créée
                        $this->dispatch('conversationSelected', $this->conversationId);

                        $this->dispatch('conversationUpdated', $this->conversationId);

                    } catch (\Exception $e) {
                        Log::error('DocumentUploader: erreur lors de la création de la conversation: '.$e->getMessage(), [
                            'exception' => get_class($e),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                // Associer le document à la conversation courante si une conversation est sélectionnée
                if ($this->conversationId) {
                    $conversation = Conversation::find($this->conversationId);

                    if ($conversation) {
                        ConversationDocument::create([
                            'conversation_id' => $this->conversationId,
                            'document_id' => $documentId,
                        ]);

                        Log::info('DocumentUploader: document associé à la conversation', [
                            'conversation_id' => $this->conversationId,
                            'document_id' => $documentId,
                        ]);
                    }
                } else {
                    Log::info('DocumentUploader: aucune conversation sélectionnée, document non associé');
                }

                $this->statusMessage = 'Document traité avec succès !';
                $this->success = true;
                $this->reset(['document', 'title']);

                // Informer les autres composants qu'un document a été ajouté
                $this->dispatch('documentAdded', $documentId);

                // Fermer le modal
                $this->dispatch('closeModalAfterDelay');
            } else {
                Log::error('DocumentUploader: échec du traitement du document');
                $this->statusMessage = 'Erreur lors du traitement du document.';
            }
        } catch (\Exception $e) {
            Log::error('DocumentUploader: erreur lors du traitement du document: '.$e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->statusMessage = 'Une erreur est survenue: '.$e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    public function render()
    {
        return view('livewire.document-uploader');
    }
}
