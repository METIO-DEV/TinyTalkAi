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
     * Récupère tous les messages de la conversation depuis le dernier résumé
     *
     * Cette méthode retourne tous les échanges (paires user/assistant)
     * de la conversation spécifiée depuis le dernier résumé, formatés pour être utilisés comme contexte.
     *
     * @param  int  $conversationId  ID de la conversation
     * @return array Tableau contenant les messages de la conversation depuis le dernier résumé
     */
    public function getLocalWindow(int $conversationId): array
    {
        // Récupérer la conversation
        $conversation = Conversation::find($conversationId);
        if (! $conversation) {
            return [];
        }

        // Déterminer à partir de quel message récupérer l'historique
        $query = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc');

        // Si un résumé a été fait et qu'un message_id de référence existe,
        // ne récupérer que les messages depuis ce point
        if ($conversation->summary_flag && $conversation->summary_message_id) {
            $query->where('id', '>=', $conversation->summary_message_id);
        }

        // Récupérer les messages
        $messages = $query->get();

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
     * Vérifie si un résumé est nécessaire en fonction du pourcentage de tokens utilisés
     *
     * @param  int  $conversationId  ID de la conversation
     * @param  int  $tokensUsed  Nombre de tokens utilisés
     * @param  int  $tokenLimit  Limite de tokens du modèle
     * @return bool True si un résumé est nécessaire
     */
    public function shouldSummarize(int $conversationId, int $tokensUsed, int $tokenLimit): bool
    {
        // Si le pourcentage de tokens utilisés dépasse 90%, un résumé est nécessaire
        $tokenPercentage = ($tokensUsed / $tokenLimit) * 100;

        return $tokenPercentage >= 90;
    }

    /**
     * Met à jour le résumé d'une conversation en fonction de tous les messages
     *
     * @param  int  $conversationId  ID de la conversation
     * @return bool Succès de la mise à jour
     */
    public function updateSummary(int $conversationId): bool
    {
        // Récupérer la conversation
        $conversation = Conversation::find($conversationId);
        if (! $conversation) {
            Log::error('Conversation non trouvée pour la mise à jour du résumé', ['conversation_id' => $conversationId]);

            return false;
        }

        // Récupérer le résumé actuel
        $currentSummary = $conversation->summary ?? 'Pas de résumé disponible.';

        // Récupérer tous les messages pertinents pour le résumé
        $query = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc');

        // Si un résumé a déjà été fait, ne récupérer que les messages depuis le dernier résumé
        if ($conversation->summary_flag && $conversation->summary_message_id) {
            $query->where('id', '>', $conversation->summary_message_id);
        }

        $messages = $query->get();

        // S'il n'y a pas de nouveaux messages depuis le dernier résumé, pas besoin de mettre à jour
        if ($conversation->summary_flag && $messages->isEmpty()) {
            Log::info('Pas de nouveaux messages depuis le dernier résumé, pas de mise à jour nécessaire');

            return true;
        }

        // Formater les messages pour le prompt
        $messagesContent = '';
        foreach ($messages as $message) {
            $role = $message->role === 'user' ? 'Utilisateur' : 'Assistant';
            $messagesContent .= "{$role}: {$message->content}\n\n";
        }

        // Configuration de l'API Ollama
        $ollamaHost = config('services.ollama.host', 'host.docker.internal');
        $ollamaPort = config('services.ollama.port', '11434');
        $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/chat';

        // Utiliser le même modèle que celui de la conversation
        $modelName = $conversation->model_name;

        try {
            // Construire les messages pour le résumé en respectant la structure de l'API
            $promptMessages = [
                [
                    'role' => 'system',
                    'content' => "Tu es un assistant spécialisé dans la création de résumés structurés et concis. Ta tâche est de mettre à jour un résumé existant d'une conversation en y intégrant les nouveaux messages. Le résumé doit être clair, organisé et capturer les points clés de la conversation.",
                ],
                [
                    'role' => 'user',
                    'content' => "Voici le résumé actuel de la conversation:\n\n$currentSummary\n\nVoici les messages à intégrer dans le résumé:\n\n$messagesContent\n\nCrée un résumé structuré qui intègre ces nouveaux messages avec le résumé existant. Le résumé doit:\n1. Commencer par 'RÉSUMÉ DE CONVERSATION:'\n2. Être organisé par thèmes ou sujets principaux avec des sous-titres en gras\n3. Inclure les points clés et décisions importantes\n4. Être synthétique mais complet (maximum 200-250 mots)\n5. Utiliser des listes à puces pour les informations importantes\n\nFormate le résumé de façon claire et lisible avec du Markdown.",
                ],
            ];

            // Appeler l'API pour générer le résumé
            $response = Http::timeout(300)->post($ollamaUrl, [
                'model' => $modelName,
                'messages' => $promptMessages,
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
                    // Récupérer le dernier message pour définir summary_message_id
                    $lastMessage = Message::where('conversation_id', $conversationId)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    // Récupérer les informations de tokens depuis la réponse de l'API
                    $summaryTokens = $response->json('eval_count', 0);

                    // Mettre à jour le résumé dans la base de données
                    $conversation->summary = $newSummary;
                    $conversation->summary_flag = true;
                    // Réinitialiser le compteur de tokens avec uniquement la taille du résumé
                    $conversation->tokens = $summaryTokens;

                    if ($lastMessage) {
                        $conversation->summary_message_id = $lastMessage->id;
                    }
                    $conversation->save();

                    Log::info('Résumé de conversation mis à jour', [
                        'conversation_id' => $conversationId,
                        'summary_length' => strlen($newSummary),
                        'summary_tokens' => $summaryTokens,
                        'summary_message_id' => $conversation->summary_message_id,
                        'messages_count' => $messages->count(),
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
