<?php

namespace App\Services;

use App\Models\UserAIProviderAccount;

class OpenAIModelSyncService
{
    public function __construct(private readonly AIProviderModelSyncService $sync) {}

    public function sync(UserAIProviderAccount $account): array
    {
        return $this->sync->sync($account);
    }

    public static function supportedModelIds(): array
    {
        return app(AIProviderConfigService::class)->catalog('openai')
            ? array_keys(app(AIProviderConfigService::class)->catalog('openai'))
            : [];
    }
}
