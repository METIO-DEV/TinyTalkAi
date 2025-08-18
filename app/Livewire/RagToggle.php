<?php

namespace App\Livewire;

use Livewire\Component;

class RagToggle extends Component
{
    /**
     * État du toggle RAG
     */
    public bool $enabled = false;

    /**
     * Listeners, écoute les évènements
     */
    protected $listeners = [
        'collectionSelected' => 'onCollectionSelected',
    ];

    /**
     * Initialisation du composant
     */
    public function mount()
    {
        $this->enabled = session('rag_enabled', false);
    }

    /**
     * Bascule l'état du toggle RAG
     */
    public function toggle()
    {
        $this->enabled = ! $this->enabled;
        session(['rag_enabled' => $this->enabled]);

        // Informer les autres composants du changement d'état
        $this->dispatch('ragToggled', $this->enabled);
    }

    /**
     * Réaction automatique quand une collection est choisie
     */
    public function onCollectionSelected(?string $collectionName): void
    {
        // Si une collection est sélectionnée ET que le mode rag est OFF,
        // on l’active silencieusement.
        if ($collectionName !== null && $this->enabled === false) {
            $this->enabled = true;
            session(['rag_enabled' => true]);
            // Inutile de rediffuser l’événement : Collection vient juste de le faire
            $this->dispatch('$refresh'); // pour rafraîchir ce composant uniquement
        }
    }

    public function render()
    {
        return view('livewire.rag-toggle');
    }
}
