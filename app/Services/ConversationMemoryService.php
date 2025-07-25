<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;

/**
 * Service responsable de la gestion de la mémoire conversationnelle
 * 
 * Ce service implémente trois types de mémoire :
 * 1. Fenêtre locale (N derniers tours) - toujours lue, décalée automatiquement
 * 2. Résumé évolutif (~100 mots) - mis à jour après chaque réponse
 * 3. Mémoire vectorielle (index d'embeddings) - mise à jour après chaque message
 */
class ConversationMemoryService
{
    /**
     * Nombre de tours de conversation à conserver dans la fenêtre locale
     */
    protected int $windowSize = 8;
    
    /**
     * Récupère les N derniers tours de conversation (fenêtre locale)
     * 
     * Cette méthode retourne les N derniers échanges (paires user/assistant)
     * de la conversation spécifiée, formatés pour être utilisés comme contexte.
     * 
     * @param int $conversationId ID de la conversation
     * @param int|null $windowSize Taille de la fenêtre (nombre de tours), utilise la valeur par défaut si null
     * @return array Tableau contenant les N derniers tours de conversation
     */
    public function getLocalWindow(int $conversationId, ?int $windowSize = null): array
    {
        // Utiliser la taille spécifiée ou la valeur par défaut
        $size = $windowSize ?? $this->windowSize;
        
        // Récupérer la conversation
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return [];
        }
        
        // Récupérer les messages de la conversation, ordonnés par date de création
        // Limiter à 2 * $size car un tour complet = message utilisateur + réponse assistant
        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->limit(2 * $size)
            ->get()
            ->reverse(); // Inverser pour avoir l'ordre chronologique
        
        // Formater les messages pour le contexte
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message->role,
                'content' => $message->content
            ];
        }
        
        return $formattedMessages;
    }
    
    /**
     * Définit la taille de la fenêtre locale
     * 
     * @param int $size Nombre de tours à conserver
     * @return self
     */
    public function setWindowSize(int $size): self
    {
        $this->windowSize = $size;
        return $this;
    }
}
