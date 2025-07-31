<?php

namespace App\Livewire;

use Livewire\Component;

class RagToggle extends Component
{
    /**
     * État du toggle RAG
     */
    public bool $enabled = true;

    /**
     * Initialisation du composant
     */
    public function mount()
    {
        $this->enabled = session('rag_enabled', true);
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

    public function render()
    {
        return view('livewire.rag-toggle');
    }
}
