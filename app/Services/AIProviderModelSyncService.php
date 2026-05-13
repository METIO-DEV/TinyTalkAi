<?php

namespace App\Services;

use App\Models\AIModel;
use App\Models\UserAIProviderAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIProviderModelSyncService
{
    public function __construct(
        private readonly AIProviderAccountService $accounts,
        private readonly AIProviderConfigService $providers,
    ) {}

    public function sync(UserAIProviderAccount $account): array
    {
        $provider = $account->provider;
        $catalog = $this->providers->catalog($provider);
        $stats = [
            'created' => 0,
            'updated' => 0,
            'deactivated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        if ($catalog === []) {
            return $stats;
        }

        try {
            $config = $this->providers->get($provider);
            $response = Http::withHeaders($this->accounts->headers($account))
                ->timeout(20)
                ->get($this->providers->baseUrl($provider).($config['models_path'] ?? '/models'));

            if (! $response->successful()) {
                $stats['errors']++;
                Log::warning('AI provider model sync failed', [
                    'provider' => $provider,
                    'user_id' => $account->user_id,
                    'status' => $response->status(),
                    'message' => $response->json('error.message') ?: $response->json('message'),
                ]);

                return $stats;
            }

            $availableIds = collect($response->json('data', []))
                ->pluck('id')
                ->filter()
                ->values()
                ->all();

            if ($availableIds !== []) {
                $stats['deactivated'] = AIModel::query()
                    ->where('provider', $provider)
                    ->where('family', 'llm')
                    ->whereNotIn('full_name', $availableIds)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            foreach ($catalog as $id => $metadata) {
                if ($availableIds !== [] && ! in_array($id, $availableIds, true)) {
                    $stats['skipped']++;

                    continue;
                }

                $record = AIModel::query()
                    ->where('provider', $provider)
                    ->where('full_name', $id)
                    ->first();

                $payload = [
                    'provider' => $provider,
                    'name' => $metadata['label'],
                    'full_name' => $id,
                    'size' => null,
                    'family' => 'llm',
                    'context_window' => $metadata['context_window'] ?? null,
                    'max_output_tokens' => $metadata['max_output_tokens'] ?? null,
                    'capabilities' => [
                        'streaming' => true,
                        'reasoning' => (bool) ($metadata['reasoning'] ?? false),
                        'reasoning_efforts' => $metadata['reasoning_efforts'] ?? null,
                        'text' => true,
                        'vision' => (bool) ($metadata['vision'] ?? false),
                    ],
                    'is_active' => true,
                    'last_synced_at' => now(),
                ];

                if ($record) {
                    $record->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    AIModel::query()->create($payload);
                    $stats['created']++;
                }
            }
        } catch (\Throwable $e) {
            $stats['errors']++;
            Log::warning('AI provider model sync exception', [
                'provider' => $provider,
                'user_id' => $account->user_id,
                'message' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    public function supportedModelIds(string $provider): array
    {
        return array_keys($this->providers->catalog($provider));
    }
}
