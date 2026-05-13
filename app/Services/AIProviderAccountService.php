<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAIProviderAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AIProviderAccountService
{
    public function __construct(protected ?AIProviderConfigService $providers = null)
    {
        $this->providers ??= app(AIProviderConfigService::class);
    }

    public function providers(): AIProviderConfigService
    {
        return $this->providers;
    }

    public function accountFor(User $user, string $provider): ?UserAIProviderAccount
    {
        $this->ensureKnownProvider($provider);

        return UserAIProviderAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();
    }

    public function connectedAccountFor(User $user, string $provider): ?UserAIProviderAccount
    {
        $this->ensureKnownProvider($provider);

        return UserAIProviderAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->where('status', 'connected')
            ->first();
    }

    public function upsert(User $user, string $provider, array $data): UserAIProviderAccount
    {
        $config = $this->ensureKnownProvider($provider);

        $account = UserAIProviderAccount::query()->firstOrNew([
            'user_id' => $user->id,
            'provider' => $provider,
        ]);

        $account->fill([
            'label' => $data['label'] ?? $config['label'],
            'organization_id' => ! empty($config['supports_organization']) ? ($data['organization_id'] ?? null) : null,
            'project_id' => ! empty($config['supports_project']) ? ($data['project_id'] ?? null) : null,
        ]);

        if (! empty($data['api_key'])) {
            $account->setApiKey($data['api_key']);
        }

        $test = $this->test($account);
        $account->status = $test['ok'] ? 'connected' : 'error';
        $account->last_verified_at = now();
        $account->last_error = $test['ok'] ? null : $test['message'];
        $account->save();

        if ($test['ok']) {
            app(AIProviderModelSyncService::class)->sync($account);
        }

        return $account;
    }

    public function test(UserAIProviderAccount $account): array
    {
        $apiKey = $account->apiKey();
        $provider = $account->provider;
        $config = $this->ensureKnownProvider($provider);
        $label = $config['label'];

        if (! $apiKey) {
            return [
                'ok' => false,
                'message' => __(':provider API key is missing.', ['provider' => $label]),
            ];
        }

        try {
            $response = Http::withHeaders($this->headers($account))
                ->timeout(15)
                ->get($this->baseUrl($provider).($config['models_path'] ?? '/models'));

            if ($response->successful()) {
                return ['ok' => true, 'message' => __(':provider account connected.', ['provider' => $label])];
            }

            return [
                'ok' => false,
                'message' => $response->json('error.message')
                    ?: $response->json('message')
                    ?: __(':provider rejected the credentials.', ['provider' => $label]),
            ];
        } catch (\Throwable $e) {
            Log::warning('AI provider account test failed', [
                'provider' => $provider,
                'user_id' => $account->user_id,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => __('Unable to reach :provider.', ['provider' => $label]),
            ];
        }
    }

    public function disconnect(User $user, string $provider): void
    {
        $this->accountFor($user, $provider)?->delete();
    }

    public function headers(UserAIProviderAccount $account): array
    {
        $config = $this->ensureKnownProvider($account->provider);
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (($config['auth_type'] ?? 'bearer') === 'anthropic_key') {
            $headers['x-api-key'] = (string) $account->apiKey();
            $headers['anthropic-version'] = (string) $this->providers->apiVersion($account->provider);

            return $headers;
        }

        $headers['Authorization'] = 'Bearer '.$account->apiKey();

        if ($account->provider === 'openai') {
            if ($account->organization_id) {
                $headers['OpenAI-Organization'] = $account->organization_id;
            }

            if ($account->project_id) {
                $headers['OpenAI-Project'] = $account->project_id;
            }
        }

        return $headers;
    }

    public function curlHeaders(UserAIProviderAccount $account): array
    {
        return collect($this->headers($account))
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->values()
            ->all();
    }

    public function baseUrl(string $provider): string
    {
        return $this->providers->baseUrl($provider);
    }

    public function accountPayloads(?User $user): array
    {
        $accounts = $user
            ? UserAIProviderAccount::query()
                ->where('user_id', $user->id)
                ->whereIn('provider', $this->providers->ids())
                ->get()
                ->keyBy('provider')
            : collect();

        $payload = [];

        foreach ($this->providers->all() as $provider => $config) {
            $account = $accounts->get($provider);
            $payload[$provider] = [
                'id' => $provider,
                'label' => $config['label'],
                'connected' => $account?->status === 'connected',
                'status' => $account->status ?? 'disconnected',
                'keyPreview' => $account?->keyPreview(),
                'organizationId' => $account?->organization_id,
                'projectId' => $account?->project_id,
                'lastVerifiedAt' => optional($account?->last_verified_at)->toIso8601String(),
                'lastError' => $account?->last_error,
                'supportsOrganizationId' => (bool) ($config['supports_organization'] ?? false),
                'supportsProjectId' => (bool) ($config['supports_project'] ?? false),
            ];
        }

        return $payload;
    }

    protected function ensureKnownProvider(string $provider): array
    {
        $config = $this->providers->get($provider);

        if (! $config) {
            throw new InvalidArgumentException("Unknown AI provider [{$provider}].");
        }

        return $config;
    }
}
