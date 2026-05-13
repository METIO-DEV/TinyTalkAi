<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class UserAIProviderAccount extends Model
{
    protected $table = 'user_ai_provider_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'label',
        'encrypted_api_key',
        'organization_id',
        'project_id',
        'status',
        'last_verified_at',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'last_verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setApiKey(?string $apiKey): void
    {
        $apiKey = trim((string) $apiKey);

        if ($apiKey === '') {
            return;
        }

        $this->encrypted_api_key = Crypt::encryptString($apiKey);
        $this->metadata = [
            ...($this->metadata ?? []),
            'key_preview' => str_repeat('*', 4).substr($apiKey, -4),
        ];
    }

    public function apiKey(): ?string
    {
        if (! $this->encrypted_api_key) {
            return null;
        }

        return Crypt::decryptString($this->encrypted_api_key);
    }

    public function keyPreview(): ?string
    {
        return $this->metadata['key_preview'] ?? null;
    }
}
