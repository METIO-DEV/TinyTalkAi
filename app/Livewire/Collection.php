<?php

namespace App\Livewire;

use App\Services\QdrantCollectionsService;
use App\Services\RagService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Collection extends Component
{
    use WithFileUploads;

    /**
     * Nom de la collection à créer
     */
    public string $collectionName = '';

    /**
     * Liste des collections disponibles
     */
    public array $collections = [];

    /**
     * Collection sélectionnée
     */
    public ?string $selectedCollection = null;

    /**
     * Indicateur de chargement pendant le traitement
     */
    public bool $isProcessing = false;

    /**
     * Indique si le modal de création est ouvert
     */
    public bool $isCreateModalOpen = false;

    /**
     * Indique si le modal d'upload est ouvert
     */
    public bool $isUploadModalOpen = false;

    /**
     * Message de statut
     */
    public string $statusMessage = '';

    /**
     * Indique si l'opération a réussi
     */
    public bool $success = false;

    /**
     * Le fichier à uploader
     */
    public $document;

    /**
     * Le titre du document
     */
    public string $documentTitle = '';

    /**
     * Le modèle d'embedding à utiliser
     */
    public string $embeddingModel = '';

    /**
     * Service de gestion des collections Qdrant
     */
    protected QdrantCollectionsService $collectionsService;

    /**
     * Service RAG
     */
    protected RagService $ragService;

    /**
     * Constructeur du composant
     */
    public function boot(QdrantCollectionsService $collectionsService, RagService $ragService)
    {
        $this->collectionsService = $collectionsService;
        $this->ragService = $ragService;
    }

    /**
     * Initialisation du composant
     */
    public function mount()
    {
        $this->loadCollections();
        // Récupérer le modèle d'embedding depuis la configuration
        $this->embeddingModel = config('services.ollama.embedding_model', 'nomic-embed-text:latest');
    }

    /**
     * Règles de validation
     */
    protected function rules()
    {
        return [
            'collectionName' => 'required|string|min:3|max:50|regex:/^[a-z0-9_]+$/',
        ];
    }

    /**
     * Messages de validation
     */
    protected function messages()
    {
        return [
            'collectionName.required' => 'Le nom de la collection est requis.',
            'collectionName.min' => 'Le nom de la collection doit contenir au moins 3 caractères.',
            'collectionName.max' => 'Le nom de la collection ne doit pas dépasser 50 caractères.',
            'collectionName.regex' => 'Le nom de la collection ne doit contenir que des lettres minuscules, des chiffres et des underscores.',
        ];
    }

    /**
     * Écouteurs d'événements
     */
    protected $listeners = [
        'refreshCollections' => 'loadCollections',
        'closeUploadModal' => 'closeUploadModal',
        'closeUploadModalAfterDelay' => 'closeUploadModalAfterDelay',
        'ragToggled' => 'onRagToggled',
    ];

    /**
     * Charge la liste des collections disponibles
     */
    public function loadCollections()
    {
        try {
            $this->isProcessing = true;
            $this->collections = $this->collectionsService->listCollections();
            $this->isProcessing = false;
        } catch (\Exception $e) {
            Log::error('Collection: erreur lors du chargement des collections: '.$e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->statusMessage = 'Erreur lors du chargement des collections: '.$e->getMessage();
            $this->isProcessing = false;
        }
    }

    /**
     * Ouvre le modal de création de collection
     */
    public function openCreateModal()
    {
        $this->isCreateModalOpen = true;
        $this->resetForm();
    }

    /**
     * Ferme le modal de création de collection
     */
    public function closeCreateModal()
    {
        $this->isCreateModalOpen = false;
        $this->resetForm();
    }

    /**
     * Réinitialise le formulaire
     */
    public function resetForm()
    {
        $this->collectionName = '';
        $this->documentTitle = '';
        $this->document = null;
        $this->statusMessage = '';
        $this->success = false;
        $this->resetValidation();
    }

    /**
     * Crée une nouvelle collection
     */
    public function createCollection()
    {
        try {
            $this->validate();

            $this->isProcessing = true;
            $this->statusMessage = 'Création de la collection en cours...';
            $this->success = false;

            // Création de la collection dans Qdrant
            $result = $this->collectionsService->createCollection($this->collectionName);

            if ($result) {
                Log::info('Collection: collection créée avec succès', [
                    'collectionName' => $this->collectionName,
                ]);

                $this->statusMessage = 'Collection créée avec succès !';
                $this->success = true;
                $this->loadCollections();
                $this->selectedCollection = $this->collectionName;

                // Fermer le modal après un court délai
                $this->dispatch('closeModalAfterDelay');
            } else {
                Log::error('Collection: échec de la création de la collection');
                $this->statusMessage = 'Erreur lors de la création de la collection.';
            }
        } catch (\Exception $e) {
            Log::error('Collection: erreur lors de la création de la collection: '.$e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->statusMessage = 'Une erreur est survenue: '.$e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    /** ➋ — appelé lorsqu’on bascule le toggle RAG */
    public function onRagToggled(bool $enabled): void
    {
        // Si on vient de passer RAG à OFF et qu’une collection était sélectionnée,
        // on la désélectionne et on en informe les autres composants.
        if (! $enabled && $this->selectedCollection !== null) {
            $this->selectedCollection = null;
            $this->dispatch('collectionSelected', null);
        }
    }

    /**
     * Sélectionne ou désélectionne une collection
     */
    public function selectCollection(string $collectionName)
    {
        if ($this->selectedCollection === $collectionName) {
            // Désélection : on ne touche PAS au toggle RAG
            $this->selectedCollection = null;
            $this->dispatch('collectionSelected', null);

            return;
        }

        // Nouvelle sélection
        $this->selectedCollection = $collectionName;
        $this->collectionsService->setQdrantCollection($collectionName);

        // On force le toggle RAG à ON pour tous les autres composants
        $this->dispatch('collectionSelected', $collectionName);       // pour l’UI
        $this->dispatch('ragToggled', true);                          // pour RagToggle
    }

    /**
     * Supprime une collection
     */
    public function deleteCollection(string $collectionName)
    {
        try {
            $this->isProcessing = true;
            $result = $this->collectionsService->deleteCollection($collectionName);

            if ($result) {
                Log::info('Collection: collection supprimée avec succès', [
                    'collectionName' => $collectionName,
                ]);

                $this->statusMessage = 'Collection supprimée avec succès !';
                $this->success = true;

                // Si la collection supprimée était sélectionnée, réinitialiser la sélection
                if ($this->selectedCollection === $collectionName) {
                    $this->selectedCollection = null;
                }

                $this->loadCollections();
            } else {
                Log::error('Collection: échec de la suppression de la collection');
                $this->statusMessage = 'Erreur lors de la suppression de la collection.';
            }
        } catch (\Exception $e) {
            Log::error('Collection: erreur lors de la suppression de la collection: '.$e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->statusMessage = 'Une erreur est survenue: '.$e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Ferme le modal après un délai
     */
    public function closeModalAfterDelay()
    {
        // délai de 1 seconde
        sleep(1);
        $this->isCreateModalOpen = false;
        $this->resetForm();
    }

    /**
     * Ouvre le modal d'upload de document
     */
    public function openUploadModal(string $collectionName)
    {
        $this->selectedCollection = $collectionName;
        $this->isUploadModalOpen = true;
        $this->resetForm();
    }

    /**
     * Ferme le modal d'upload de document
     */
    public function closeUploadModal()
    {
        $this->isUploadModalOpen = false;
        $this->resetForm();
    }

    /**
     * Ferme le modal d'upload après un délai
     */
    public function closeUploadModalAfterDelay()
    {
        // délai de 1 seconde
        sleep(1);
        $this->isUploadModalOpen = false;
        $this->resetForm();
    }

    /**
     * Règles de validation pour l'upload de document
     */
    protected function documentRules()
    {
        return [
            'document' => 'required|file|mimes:txt,pdf,docx|max:10240', // 10MB max
            'documentTitle' => 'required|string|max:255',
        ];
    }

    /**
     * Messages de validation pour l'upload de document
     */
    protected function documentMessages()
    {
        return [
            'document.required' => 'Veuillez sélectionner un fichier.',
            'document.file' => 'Le fichier est invalide.',
            'document.mimes' => 'Seuls les fichiers .txt, .pdf et .docx sont acceptés.',
            'document.max' => 'La taille du fichier ne doit pas dépasser 10MB.',
            'documentTitle.required' => 'Le titre du document est requis.',
            'documentTitle.max' => 'Le titre ne doit pas dépasser 255 caractères.',
        ];
    }

    /**
     * Upload et traitement du document
     */
    public function uploadDocument()
    {
        try {
            $this->validate($this->documentRules(), $this->documentMessages());

            $this->isProcessing = true;
            $this->statusMessage = 'Traitement en cours...';
            $this->success = false;

            // Générer un ID unique pour le document
            $documentId = (string) Str::uuid();

            // Stocker le fichier
            $path = $this->document->store('documents', 'public');
            Log::info('Collection: fichier stocké', ['path' => $path]);

            // Obtenir le chemin complet du fichier
            $fullPath = Storage::disk('public')->path($path);
            $mimeType = $this->document->getMimeType();

            // Extraire le contenu selon le type de fichier
            $content = '';

            if (in_array($mimeType, ['application/pdf'])) {
                // Traitement des PDF
                $content = $this->extractTextFromPdf($fullPath);
            } elseif (in_array($mimeType, ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'])) {
                // Traitement des DOCX/DOC
                $content = $this->extractTextFromDocx($fullPath);
            } else {
                // Traitement des fichiers texte
                $content = Storage::disk('public')->get($path);
            }

            // Métadonnées du document
            $metadata = [
                'title' => $this->documentTitle,
                'filename' => $this->document->getClientOriginalName(),
                'collection' => $this->selectedCollection,
                'uploaded_at' => now()->toIso8601String(),
            ];

            // S'assurer que la collection est sélectionnée dans les deux services
            $this->collectionsService->setQdrantCollection($this->selectedCollection);

            // Définir le modèle d'embedding à utiliser
            $this->ragService->setEmbeddingModel($this->embeddingModel);

            // Traiter le document avec le service RAG
            Log::info('Collection: appel à processDocument', [
                'documentId' => $documentId,
                'contentLength' => strlen($content),
                'metadata' => $metadata,
                'collection' => $this->selectedCollection,
            ]);

            $success = $this->ragService->processDocument($documentId, $content, $metadata, $this->selectedCollection);

            if ($success) {
                Log::info('Collection: document traité avec succès');
                $this->statusMessage = 'Document traité avec succès !';
                $this->success = true;
                $this->reset(['document', 'documentTitle']);

                // Fermer le modal
                $this->dispatch('closeUploadModalAfterDelay');
            } else {
                Log::error('Collection: échec du traitement du document');
                $this->statusMessage = 'Erreur lors du traitement du document.';
            }
        } catch (\Exception $e) {
            Log::error('Collection: erreur lors du traitement du document: '.$e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->statusMessage = 'Une erreur est survenue: '.$e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Extrait le texte d'un fichier PDF
     *
     * @param  string  $filePath  Chemin complet vers le fichier PDF
     * @return string Le texte extrait du PDF
     */
    protected function extractTextFromPdf(string $filePath): string
    {
        // Vérifier si la bibliothèque est installée
        if (! class_exists('\Smalot\PdfParser\Parser')) {
            throw new \Exception('La bibliothèque smalot/pdfparser n\'est pas installée. Exécutez: composer require smalot/pdfparser');
        }

        // Créer le parser
        $parser = new \Smalot\PdfParser\Parser;

        try {
            // Analyser le PDF
            $pdf = $parser->parseFile($filePath);

            // Extraire le texte
            $text = $pdf->getText();

            return trim($text);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'extraction du texte PDF: '.$e->getMessage());
            throw new \Exception('Impossible d\'extraire le texte du PDF: '.$e->getMessage());
        }
    }

    /**
     * Extrait le texte d'un fichier DOCX/DOC
     *
     * @param  string  $filePath  Chemin complet vers le fichier DOCX/DOC
     * @return string Le texte extrait du DOCX/DOC
     */
    protected function extractTextFromDocx(string $filePath): string
    {
        // Vérifier si la bibliothèque est installée
        if (! class_exists('\PhpOffice\PhpWord\IOFactory')) {
            throw new \Exception('La bibliothèque phpoffice/phpword n\'est pas installée. Exécutez: composer require phpoffice/phpword');
        }

        try {
            // Charger le document
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);

            // Extraire le texte de chaque section
            $text = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getElements')) {
                        foreach ($element->getElements() as $childElement) {
                            if (method_exists($childElement, 'getText')) {
                                $text .= $childElement->getText().' ';
                            }
                        }
                    } elseif (method_exists($element, 'getText')) {
                        $text .= $element->getText().' ';
                    }
                }
            }

            return trim($text);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'extraction du texte DOCX: '.$e->getMessage());
            throw new \Exception('Impossible d\'extraire le texte du DOCX: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.collection');
    }
}
