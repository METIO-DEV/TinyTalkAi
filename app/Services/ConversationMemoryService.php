<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service responsable de la gestion de la mémoire conversationnelle
 *
 * Ce service implémente trois types de mémoire :
 * 1. Historique complet - tous les messages de la conversation
 * 2. Résumé évolutif (~100 mots) - mis à jour après chaque réponse
 * 3. Mémoire vectorielle (index d'embeddings) - mise à jour après chaque message
 */
class ConversationMemoryService
{
    /**
     * Récupère tous les messages de la conversation
     *
     * Cette méthode retourne tous les échanges (paires user/assistant)
     * de la conversation spécifiée, formatés pour être utilisés comme contexte.
     *
     * @param  int  $conversationId  ID de la conversation
     * @return array Tableau contenant tous les messages de la conversation
     */
    public function getLocalWindow(int $conversationId): array
    {
        // Récupérer la conversation
        $conversation = Conversation::find($conversationId);
        if (! $conversation) {
            return [];
        }

        // Récupérer tous les messages de la conversation, ordonnés par date de création
        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->reverse();

        // Formater les messages pour le contexte
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message->role, // utilisateur ou assistant
                'content' => $message->content, // contenu du message
            ];
        }

        return $formattedMessages;
    }

    /**
     * Récupère le résumé actuel d'une conversation
     *
     * @param  int  $conversationId  ID de la conversation
     * @return string|null Résumé de la conversation ou null si pas de résumé
     */
    public function getSummary(int $conversationId): ?string
    {
        $conversation = Conversation::find($conversationId);

        return $conversation ? $conversation->summary : null;
    }

    /**
     * Met à jour le résumé d'une conversation en fonction des derniers messages
     *
     * @param  int  $conversationId  ID de la conversation
     * @param  string  $userMessage  Dernier message de l'utilisateur
     * @param  string  $assistantResponse  Dernière réponse de l'assistant
     * @return bool Succès de la mise à jour
     */
    public function updateSummary(int $conversationId, string $userMessage, string $assistantResponse): bool
    {
        // Récupérer la conversation
        $conversation = Conversation::find($conversationId);
        if (! $conversation) {
            Log::error('Conversation non trouvée pour la mise à jour du résumé', ['conversation_id' => $conversationId]);

            return false;
        }

        // Récupérer le résumé actuel
        $currentSummary = $conversation->summary ?? 'Pas de résumé disponible.';

        // Configuration de l'API Ollama
        $ollamaHost = config('services.ollama.host', 'host.docker.internal');
        $ollamaPort = config('services.ollama.port', '11434');
        $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/chat';

        // Utiliser le même modèle que celui de la conversation
        $modelName = $conversation->model_name;

        try {
            // Construire les messages pour le résumé en respectant la structure de l'API
            $messages = [
                [
                    'role' => 'system',
                    'content' => "Tu es un assistant spécialisé dans la création de résumés concis. Ta tâche est de mettre à jour un résumé existant d'une conversation en y intégrant les nouveaux messages. Le résumé doit faire environ 100 mots et capturer les points clés de la conversation.",
                ],
                [
                    'role' => 'user',
                    'content' => "Voici le résumé actuel de la conversation:\n\n$currentSummary\n\nVoici le dernier échange:\n\nUtilisateur: $userMessage\n\nAssistant: $assistantResponse\n\nMets à jour le résumé en intégrant cet échange. Garde le résumé concis (environ 100 mots) et concentre-toi sur les informations importantes.",
                ],
            ];

            // Appeler l'API pour générer le résumé
            $response = Http::timeout(30)->post($ollamaUrl, [
                'model' => $modelName,
                'messages' => $messages,
                'options' => [
                    'temperature' => 0.3, // Température basse pour plus de cohérence
                    'max_tokens' => 300, // Limiter la longueur du résumé
                ],
                'stream' => false,
            ]);

            if ($response->successful()) {
                // Extraire le résumé généré selon la structure de réponse de l'API
                $newSummary = $response->json('message.content');

                if ($newSummary) {
                    // Mettre à jour le résumé dans la base de données
                    $conversation->summary = $newSummary;
                    $conversation->save();

                    Log::info('Résumé de conversation mis à jour', [
                        'conversation_id' => $conversationId,
                        'summary_length' => strlen($newSummary),
                    ]);

                    return true;
                } else {
                    Log::error('Réponse de l\'API sans contenu pour le résumé', [
                        'response' => $response->json(),
                    ]);
                }
            } else {
                Log::error('Erreur lors de la génération du résumé', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception lors de la mise à jour du résumé', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return false;
    }
}
