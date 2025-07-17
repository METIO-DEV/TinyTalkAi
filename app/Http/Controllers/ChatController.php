<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Utilisé pour faire des requêtes HTTP vers l'API Ollama
use Illuminate\Support\Facades\Log; // Utilisé pour journaliser les événements et faciliter le débogage

/**
 * Contrôleur responsable de la gestion des interactions avec l'API Ollama
 * 
 * Ce contrôleur gère deux aspects principaux :
 * 1. L'affichage de l'interface de chat avec la liste des modèles disponibles
 * 2. L'envoi des messages utilisateur à Ollama et la récupération des réponses
 */
class ChatController extends Controller
{
    /**
     * Affiche la page de chat avec la liste des modèles disponibles
     * 
     * Cette méthode est appelée lorsque l'utilisateur accède à la route '/chat'
     * Elle récupère la liste des modèles LLM disponibles via l'API Ollama
     * et les transmet à la vue pour affichage dans un menu déroulant
     * 
     * @return \Illuminate\Contracts\View\View La vue 'chat' avec les modèles disponibles
     */
    public function index()
    {
        // Récupérer la liste des modèles disponibles via Ollama
        $models = $this->getAvailableModels();

        // Passer les modèles à la vue pour affichage dans le sélecteur
        return view('chat', ['models' => $models]);
    }

    /**
     * Récupère la liste des modèles LLM disponibles via l'API Ollama
     * 
     * Cette méthode privée interroge l'endpoint '/api/tags' d'Ollama pour obtenir
     * la liste des modèles LLM téléchargés et disponibles localement.
     * 
     * Particularités techniques :
     * - Utilise la configuration depuis config/services.php avec des valeurs par défaut
     * - Le timeout est limité à 5 secondes pour éviter de bloquer l'interface
     * - Gestion complète des erreurs avec journalisation pour faciliter le débogage
     * - Retourne un tableau vide en cas d'échec pour éviter les erreurs dans la vue
     * 
     * @return array Liste des modèles disponibles ou tableau vide si erreur
     */
    private function getAvailableModels()
    {
        try {
            // Récupération des paramètres de configuration avec valeurs par défaut
            // 'host.docker.internal' permet d'accéder à la machine hôte depuis un conteneur Docker
            // Cette approche facilite le déploiement dans différents environnements
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434'); // Port par défaut d'Ollama
            $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/tags'; // Endpoint pour lister les modèles

            // Journalisation pour faciliter le débogage et le monitoring
            Log::info('Tentative de connexion à Ollama: '.$ollamaUrl);
            
            // Requête HTTP avec timeout court pour éviter de bloquer l'interface utilisateur
            $response = Http::timeout(5)->get($ollamaUrl);

            // Vérification du succès de la requête (code 2xx)
            if ($response->successful()) {
                Log::info('Connexion à Ollama réussie');

                // Extraction des modèles depuis la réponse JSON
                // L'API Ollama renvoie les modèles sous la clé 'models'
                return $response->json('models');
            }

            // Journalisation en cas d'échec avec le code d'état HTTP
            Log::error('Échec de la connexion à Ollama: '.$response->status());

            // Retour d'un tableau vide pour éviter les erreurs dans la vue
            return [];
        } catch (\Exception $e) {
            // Capture de toutes les exceptions possibles (réseau, timeout, etc.)
            Log::error('Erreur lors de la récupération des modèles Ollama: '.$e->getMessage());

            // Retour d'un tableau vide pour assurer la robustesse
            return [];
        }
    }

    /**
     * Envoie un message utilisateur à Ollama et récupère la réponse générée par le LLM
     * 
     * Cette méthode est appelée via une requête AJAX depuis l'interface de chat.
     * Elle traite le message de l'utilisateur, le transmet au modèle LLM sélectionné
     * via l'API Ollama, puis renvoie la réponse générée au format JSON.
     * 
     * Particularités techniques :
     * - Validation complète des entrées utilisateur pour sécuriser l'API
     * - Paramètres de génération configurables (température, max_tokens)
     * - Timeout plus long (30s) pour permettre aux modèles de générer des réponses complètes
     * - Mode non-streaming pour simplifier la gestion des réponses
     * - Gestion détaillée des erreurs avec codes HTTP appropriés
     * 
     * @param Request $request Requête HTTP contenant le message et les paramètres
     * @return \Illuminate\Http\JsonResponse Réponse JSON contenant la réponse du LLM ou un message d'erreur
     */
    public function sendMessage(Request $request)
    {
        // Validation des entrées utilisateur pour sécuriser l'API
        // Ces règles garantissent que les paramètres sont du bon type et dans les limites acceptables
        $request->validate([
            'message' => 'required|string',            // Le message ne peut pas être vide
            'model' => 'required|string',             // Le nom du modèle LLM à utiliser
            'temperature' => 'numeric|min:0|max:2',    // Contrôle la créativité/aléa des réponses
            'max_tokens' => 'integer|min:1|max:4096',  // Limite la longueur de la réponse
        ]);

        // Récupération des paramètres avec valeurs par défaut si non spécifiés
        // 0.7 est une température équilibrée entre cohérence et créativité
        $temperature = $request->input('temperature', 0.7);
        // 1024 tokens est une longueur raisonnable pour la plupart des réponses
        $maxTokens = $request->input('max_tokens', 1024);

        try {
            // Configuration de l'URL de l'API Ollama pour la génération de texte
            // Même logique que dans getAvailableModels() pour la compatibilité Docker
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = 'http://'.$ollamaHost.':'.$ollamaPort.'/api/generate'; // Endpoint de génération

            // Journalisation détaillée pour le monitoring et le débogage
            Log::info('Envoi d\'une requête à Ollama: '.$ollamaUrl);
            Log::info('Modèle: '.$request->input('model').', Température: '.$temperature.', Max tokens: '.$maxTokens);

            // Requête POST vers l'API Ollama avec un timeout plus long (30s)
            // La génération de texte peut prendre du temps selon le modèle et la longueur demandée
            $response = Http::timeout(30)->post($ollamaUrl, [
                'model' => $request->input('model'),              // Nom du modèle LLM à utiliser
                'prompt' => $request->input('message'),           // Message de l'utilisateur
                'temperature' => (float) $temperature,            // Conversion explicite en float
                'max_tokens' => (int) $maxTokens,                // Conversion explicite en integer
                'stream' => false,                               // Mode non-streaming pour simplifier la gestion
            ]);

            // Vérification du succès de la requête (code 2xx)
            if ($response->successful()) {
                Log::info('Réponse reçue d\'Ollama avec succès');

                // Renvoi de la réponse au format JSON pour traitement côté client
                // L'API Ollama renvoie le texte généré sous la clé 'response'
                return response()->json([
                    'success' => true,
                    'response' => $response->json('response'),  // Extraction du texte généré
                ]);
            }

            // Journalisation détaillée en cas d'échec
            Log::error('Échec de la communication avec Ollama: '.$response->status());
            Log::error($response->body());  // Journalisation du corps complet pour débogage

            // Renvoi d'une erreur 500 avec message explicatif
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la communication avec Ollama: '.$response->status(),
            ], 500);  // Code 500 pour indiquer une erreur serveur
        } catch (\Exception $e) {
            // Capture de toutes les exceptions possibles (réseau, timeout, etc.)
            Log::error('Erreur lors de l\'envoi du message à Ollama: '.$e->getMessage());

            // Renvoi d'une erreur 500 avec le message d'exception
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la communication avec Ollama: '.$e->getMessage(),
            ], 500);
        }
    }
}
