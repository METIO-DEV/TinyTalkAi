<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Affiche la page de chat
     */
    public function index()
    {
        // Récupérer la liste des modèles disponibles via Ollama
        $models = $this->getAvailableModels();
        
        return view('chat', ['models' => $models]);
    }
    
    /**
     * Récupère la liste des modèles disponibles via Ollama
     */
    private function getAvailableModels()
    {
        try {
            // Utiliser host.docker.internal pour accéder à la machine hôte depuis Docker
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = "http://{$ollamaHost}:{$ollamaPort}/api/tags";
            
            Log::info("Tentative de connexion à Ollama: {$ollamaUrl}");
            $response = Http::timeout(5)->get($ollamaUrl);
            
            if ($response->successful()) {
                Log::info("Connexion à Ollama réussie");
                return $response->json('models');
            }
            
            Log::error("Échec de la connexion à Ollama: " . $response->status());
            return [];
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des modèles Ollama: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Envoie un message à Ollama et récupère la réponse
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'model' => 'required|string',
            'temperature' => 'numeric|min:0|max:2',
            'max_tokens' => 'integer|min:1|max:4096'
        ]);
        
        $temperature = $request->input('temperature', 0.7);
        $maxTokens = $request->input('max_tokens', 1024);
        
        try {
            // Utiliser host.docker.internal pour accéder à la machine hôte depuis Docker
            $ollamaHost = config('services.ollama.host', 'host.docker.internal');
            $ollamaPort = config('services.ollama.port', '11434');
            $ollamaUrl = "http://{$ollamaHost}:{$ollamaPort}/api/generate";
            
            Log::info("Envoi d'une requête à Ollama: {$ollamaUrl}");
            Log::info("Modèle: {$request->model}, Température: {$temperature}, Max tokens: {$maxTokens}");
            
            $response = Http::timeout(30)->post($ollamaUrl, [
                'model' => $request->model,
                'prompt' => $request->message,
                'temperature' => (float) $temperature,
                'max_tokens' => (int) $maxTokens,
                'stream' => false
            ]);
            
            if ($response->successful()) {
                Log::info("Réponse reçue d'Ollama avec succès");
                return response()->json([
                    'success' => true,
                    'response' => $response->json('response')
                ]);
            }
            
            Log::error("Échec de la communication avec Ollama: " . $response->status());
            Log::error($response->body());
            
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la communication avec Ollama: ' . $response->status()
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi du message à Ollama: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la communication avec Ollama: ' . $e->getMessage()
            ], 500);
        }
    }
}
