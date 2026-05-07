<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OllamaHealthService
{
    public function status(): array
    {
        $host = config('services.ollama.host', 'host.docker.internal');
        $port = config('services.ollama.port', '11434');
        $url = "http://{$host}:{$port}";

        try {
            $response = Http::timeout(2)->get("{$url}/api/tags");

            if ($response->successful()) {
                return [
                    'available' => true,
                    'message' => 'Ollama est accessible.',
                    'url' => $url,
                ];
            }

            return [
                'available' => false,
                'message' => "Ollama n'est pas accessible. Démarrez Ollama avant d'envoyer un message ou d'indexer des documents.",
                'url' => $url,
                'status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'message' => "Ollama n'est pas accessible. Démarrez Ollama avant d'envoyer un message ou d'indexer des documents.",
                'url' => $url,
            ];
        }
    }

    public function isAvailable(): bool
    {
        return $this->status()['available'];
    }
}
