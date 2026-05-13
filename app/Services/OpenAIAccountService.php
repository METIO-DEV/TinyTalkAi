<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAIProviderAccount;

class OpenAIAccountService extends AIProviderAccountService
{
    public function accountFor(User $user, string $provider = 'openai'): ?UserAIProviderAccount
    {
        return parent::accountFor($user, 'openai');
    }

    public function connectedAccountFor(User $user, string $provider = 'openai'): ?UserAIProviderAccount
    {
        return parent::connectedAccountFor($user, 'openai');
    }

    public function upsert(User $user, array|string $providerOrData, ?array $data = null): UserAIProviderAccount
    {
        $payload = is_array($providerOrData) ? $providerOrData : ($data ?? []);

        return parent::upsert($user, 'openai', $payload);
    }

    public function disconnect(User $user, string $provider = 'openai'): void
    {
        parent::disconnect($user, 'openai');
    }

    public function baseUrl(string $provider = 'openai'): string
    {
        return parent::baseUrl('openai');
    }
}
