<?php

namespace App\Console\Commands;

use App\Services\ModelSyncService;
use Illuminate\Console\Command;

class SyncModels extends Command
{
    protected $signature = 'models:sync {--deactivate-missing : Désactiver les modèles non présents sur Ollama}';

    protected $description = 'Synchronise les modèles de génération depuis Ollama dans la base de données';

    public function handle(ModelSyncService $service): int
    {
        $deactivate = (bool) $this->option('deactivate-missing');
        $this->info('Synchronisation des modèles depuis Ollama...');

        $stats = $service->sync($deactivate);

        $this->info("Créés: {$stats['created']}");
        $this->info("Mis à jour: {$stats['updated']}");
        $this->info("Désactivés: {$stats['deactivated']}");
        $this->info("Ignorés: {$stats['skipped']}");
        if ($stats['errors'] > 0) {
            $this->error("Erreurs: {$stats['errors']}");
        }

        return self::SUCCESS;
    }
}
