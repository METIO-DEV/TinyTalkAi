<?php

namespace App\Jobs;

use App\Models\ModelInstallation;
use App\Models\User;
use App\Services\ModelSyncService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstallOllamaModel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes

    public function __construct(
        public string $fullName,
        public int $userId,
        public ?int $installationId = null,
    ) {
    }

    public function handle(ModelSyncService $sync): void
    {
        $user = User::find($this->userId);

        // Récupérer ou créer la ligne de suivi d'installation
        $installation = $this->installationId
            ? ModelInstallation::find($this->installationId)
            : null;

        if (! $installation) {
            $installation = ModelInstallation::create([
                'user_id' => $this->userId,
                'full_name' => $this->fullName,
                'status' => 'running',
                'progress' => 0,
                'status_text' => 'Démarrage du téléchargement…',
                'started_at' => now(),
            ]);
        } else {
            $installation->update([
                'status' => 'running',
                'progress' => 0,
                'status_text' => 'Démarrage du téléchargement…',
                'started_at' => now(),
            ]);
        }

        if ($user) {
            Notification::make()
                ->title('Installation du modèle démarrée')
                ->body($this->fullName)
                ->info()
                ->sendToDatabase($user);
        }

        $host = config('services.ollama.host', 'localhost');
        $port = config('services.ollama.port', '11434');
        $url = "http://{$host}:{$port}/api/pull";

        try {
            $response = Http::withOptions(['stream' => true, 'timeout' => 0])
                ->post($url, [
                    'name' => $this->fullName,
                    'stream' => true,
                ]);

            if (! $response->successful()) {
                $this->markFailed($installation, 'Échec HTTP lors du pull: ' . $response->status());
                $this->notifyFailure($user, 'Échec HTTP lors du pull: ' . $response->status());
                return;
            }

            $body = $response->toPsrResponse()->getBody();
            $buffer = '';
            $lastPercent = (int) ($installation->progress ?? 0);
            while (! $body->eof()) {
                $chunk = $body->read(8192);
                if ($chunk === '') {
                    usleep(100000);
                    continue;
                }
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);
                    if ($line === '') {
                        continue;
                    }
                    try {
                        $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                        if (isset($data['error'])) {
                            throw new \RuntimeException((string) $data['error']);
                        }
                        // Mettre à jour le texte de statut
                        $statusText = (string) ($data['status'] ?? '');
                        $installation->status_text = $statusText;

                        // Calculer une progression si possible (précise si completed/total, sinon estimation par phase)
                        $percent = $lastPercent;
                        if (isset($data['completed'], $data['total']) && (int)$data['total'] > 0) {
                            $percent = (int) floor(((int)$data['completed'] / (int)$data['total']) * 100);
                        } else {
                            $st = strtolower($statusText);
                            if (str_contains($st, 'pulling manifest')) {
                                $percent = max($percent, 5);
                            } elseif (str_contains($st, 'pulling')) { // couches
                                $percent = max($percent, 15);
                            } elseif (str_contains($st, 'downloading')) {
                                $percent = max($percent, 40);
                            } elseif (str_contains($st, 'verifying')) {
                                $percent = max($percent, 90);
                            } elseif (str_contains($st, 'writing manifest')) {
                                $percent = max($percent, 95);
                            } elseif (str_contains($st, 'success')) {
                                $percent = max($percent, 100);
                            }
                        }

                        if ($percent !== $lastPercent) {
                            $lastPercent = $percent;
                            $installation->progress = max(0, min(100, $percent));
                        }

                        $installation->save();

                        Log::info('Ollama pull progress', [
                            'model' => $this->fullName,
                            'status' => $statusText,
                            'completed' => $data['completed'] ?? null,
                            'total' => $data['total'] ?? null,
                            'progress' => $installation->progress,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('InstallOllamaModel: ligne de stream non JSON', ['line' => $line]);
                    }
                }
            }

            // Pull terminé
            $sync->syncOne($this->fullName);

            $installation->status = 'succeeded';
            $installation->progress = 100;
            $installation->status_text = 'Installation terminée';
            $installation->finished_at = now();
            $installation->save();

            if ($user) {
                Notification::make()
                    ->title('Modèle installé')
                    ->body($this->fullName)
                    ->success()
                    ->sendToDatabase($user);
            }
        } catch (\Throwable $e) {
            Log::error('InstallOllamaModel: exception', [
                'model' => $this->fullName,
                'message' => $e->getMessage(),
            ]);
            $this->markFailed($installation, $e->getMessage());
            $this->notifyFailure($user, $e->getMessage());
        }
    }

    protected function markFailed(ModelInstallation $installation, string $reason): void
    {
        $installation->status = 'failed';
        $installation->status_text = $reason;
        $installation->finished_at = now();
        $installation->save();
    }

    protected function notifyFailure(?User $user, string $reason): void
    {
        if ($user) {
            Notification::make()
                ->title('Échec d\'installation du modèle')
                ->body($this->fullName . ' — ' . $reason)
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
