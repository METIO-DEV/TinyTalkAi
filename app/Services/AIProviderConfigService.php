<?php

namespace App\Services;

class AIProviderConfigService
{
    private const PROVIDERS = [
        'openai' => [
            'label' => 'OpenAI',
            'stream_driver' => 'openai_responses',
            'auth_type' => 'bearer',
            'base_url_config' => 'services.openai.base_url',
            'base_url_default' => 'https://api.openai.com/v1',
            'models_path' => '/models',
            'supports_organization' => true,
            'supports_project' => true,
            'default_model_config' => 'services.openai.default_model',
            'default_model' => 'gpt-5-mini',
            'catalog' => [
                'gpt-5.2' => ['label' => 'GPT-5.2', 'context_window' => 400000, 'max_output_tokens' => 128000, 'reasoning' => true, 'reasoning_efforts' => ['none', 'low', 'medium', 'high', 'xhigh'], 'vision' => true],
                'gpt-5.2-pro' => ['label' => 'GPT-5.2 pro', 'context_window' => 400000, 'max_output_tokens' => 128000, 'reasoning' => true, 'reasoning_efforts' => ['medium', 'high', 'xhigh'], 'vision' => true],
                'gpt-5.1' => ['label' => 'GPT-5.1', 'context_window' => 400000, 'max_output_tokens' => 128000, 'reasoning' => true, 'reasoning_efforts' => ['none', 'low', 'medium', 'high'], 'vision' => true],
                'gpt-5' => ['label' => 'GPT-5', 'context_window' => 400000, 'max_output_tokens' => 128000, 'reasoning' => true, 'reasoning_efforts' => ['minimal', 'low', 'medium', 'high'], 'vision' => true],
                'gpt-5-mini' => ['label' => 'GPT-5 mini', 'context_window' => 400000, 'max_output_tokens' => 128000, 'reasoning' => true, 'vision' => true],
                'gpt-5-nano' => ['label' => 'GPT-5 nano', 'context_window' => 400000, 'max_output_tokens' => 128000, 'reasoning' => true, 'vision' => true],
                'gpt-4.1' => ['label' => 'GPT-4.1', 'context_window' => 1000000, 'max_output_tokens' => 32768, 'reasoning' => false, 'vision' => true],
                'gpt-4.1-mini' => ['label' => 'GPT-4.1 mini', 'context_window' => 1000000, 'max_output_tokens' => 32768, 'reasoning' => false, 'vision' => true],
                'gpt-4.1-nano' => ['label' => 'GPT-4.1 nano', 'context_window' => 1000000, 'max_output_tokens' => 32768, 'reasoning' => false, 'vision' => true],
            ],
        ],
        'anthropic' => [
            'label' => 'Anthropic Claude',
            'stream_driver' => 'anthropic_messages',
            'auth_type' => 'anthropic_key',
            'base_url_config' => 'services.anthropic.base_url',
            'base_url_default' => 'https://api.anthropic.com/v1',
            'models_path' => '/models',
            'api_version_config' => 'services.anthropic.version',
            'api_version_default' => '2023-06-01',
            'default_model_config' => 'services.anthropic.default_model',
            'default_model' => 'claude-sonnet-4-5-20250929',
            'catalog' => [
                'claude-opus-4-1-20250805' => ['label' => 'Claude Opus 4.1', 'context_window' => 200000, 'max_output_tokens' => 32000, 'reasoning' => true, 'vision' => true],
                'claude-sonnet-4-5-20250929' => ['label' => 'Claude Sonnet 4.5', 'context_window' => 200000, 'max_output_tokens' => 64000, 'reasoning' => true, 'vision' => true],
                'claude-haiku-4-5-20251001' => ['label' => 'Claude Haiku 4.5', 'context_window' => 200000, 'max_output_tokens' => 32000, 'reasoning' => true, 'vision' => true],
                'claude-3-5-haiku-20241022' => ['label' => 'Claude 3.5 Haiku', 'context_window' => 200000, 'max_output_tokens' => 8192, 'reasoning' => false, 'vision' => true],
            ],
        ],
        'mistral' => [
            'label' => 'Mistral AI',
            'stream_driver' => 'openai_chat',
            'auth_type' => 'bearer',
            'base_url_config' => 'services.mistral.base_url',
            'base_url_default' => 'https://api.mistral.ai/v1',
            'models_path' => '/models',
            'default_model_config' => 'services.mistral.default_model',
            'default_model' => 'mistral-large-latest',
            'catalog' => [
                'mistral-large-latest' => ['label' => 'Mistral Large', 'context_window' => 128000, 'max_output_tokens' => 8192, 'reasoning' => false, 'vision' => false],
                'mistral-medium-latest' => ['label' => 'Mistral Medium', 'context_window' => 128000, 'max_output_tokens' => 8192, 'reasoning' => false, 'vision' => false],
                'mistral-small-latest' => ['label' => 'Mistral Small', 'context_window' => 128000, 'max_output_tokens' => 8192, 'reasoning' => false, 'vision' => false],
                'codestral-latest' => ['label' => 'Codestral', 'context_window' => 256000, 'max_output_tokens' => 8192, 'reasoning' => false, 'vision' => false],
                'ministral-8b-latest' => ['label' => 'Ministral 8B', 'context_window' => 128000, 'max_output_tokens' => 8192, 'reasoning' => false, 'vision' => false],
                'ministral-3b-latest' => ['label' => 'Ministral 3B', 'context_window' => 128000, 'max_output_tokens' => 8192, 'reasoning' => false, 'vision' => false],
            ],
        ],
        'groq' => [
            'label' => 'Groq',
            'stream_driver' => 'openai_chat',
            'auth_type' => 'bearer',
            'base_url_config' => 'services.groq.base_url',
            'base_url_default' => 'https://api.groq.com/openai/v1',
            'models_path' => '/models',
            'default_model_config' => 'services.groq.default_model',
            'default_model' => 'llama-3.3-70b-versatile',
            'catalog' => [
                'llama-3.3-70b-versatile' => ['label' => 'Llama 3.3 70B', 'context_window' => 128000, 'max_output_tokens' => 32768, 'reasoning' => false, 'vision' => false],
                'llama-3.1-8b-instant' => ['label' => 'Llama 3.1 8B Instant', 'context_window' => 128000, 'max_output_tokens' => 8192, 'reasoning' => false, 'vision' => false],
                'openai/gpt-oss-120b' => ['label' => 'GPT-OSS 120B', 'context_window' => 128000, 'max_output_tokens' => 32768, 'reasoning' => true, 'vision' => false],
                'openai/gpt-oss-20b' => ['label' => 'GPT-OSS 20B', 'context_window' => 128000, 'max_output_tokens' => 32768, 'reasoning' => true, 'vision' => false],
                'qwen/qwen3-32b' => ['label' => 'Qwen 3 32B', 'context_window' => 128000, 'max_output_tokens' => 32768, 'reasoning' => true, 'vision' => false],
            ],
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'stream_driver' => 'openai_chat',
            'auth_type' => 'bearer',
            'base_url_config' => 'services.openrouter.base_url',
            'base_url_default' => 'https://openrouter.ai/api/v1',
            'models_path' => '/models',
            'default_model_config' => 'services.openrouter.default_model',
            'default_model' => 'anthropic/claude-sonnet-4.5',
            'catalog' => [
                'anthropic/claude-sonnet-4.5' => ['label' => 'Claude Sonnet 4.5', 'context_window' => 200000, 'max_output_tokens' => 64000, 'reasoning' => true, 'vision' => true],
                'anthropic/claude-opus-4.1' => ['label' => 'Claude Opus 4.1', 'context_window' => 200000, 'max_output_tokens' => 32000, 'reasoning' => true, 'vision' => true],
                'openai/gpt-4.1' => ['label' => 'GPT-4.1', 'context_window' => 1000000, 'max_output_tokens' => 32768, 'reasoning' => false, 'vision' => true],
                'google/gemini-2.5-pro' => ['label' => 'Gemini 2.5 Pro', 'context_window' => 1000000, 'max_output_tokens' => 65536, 'reasoning' => true, 'vision' => true],
                'meta-llama/llama-3.3-70b-instruct' => ['label' => 'Llama 3.3 70B', 'context_window' => 128000, 'max_output_tokens' => 8192, 'reasoning' => false, 'vision' => false],
            ],
        ],
    ];

    public function all(): array
    {
        return self::PROVIDERS;
    }

    public function ids(): array
    {
        return array_keys(self::PROVIDERS);
    }

    public function allowedModelProviders(): array
    {
        return array_merge(['ollama'], $this->ids());
    }

    public function get(string $provider): ?array
    {
        return self::PROVIDERS[$provider] ?? null;
    }

    public function label(string $provider): string
    {
        return $this->get($provider)['label'] ?? ucfirst($provider);
    }

    public function baseUrl(string $provider): string
    {
        $config = $this->get($provider);

        if (! $config) {
            return '';
        }

        return rtrim(config($config['base_url_config'], $config['base_url_default']), '/');
    }

    public function apiVersion(string $provider): ?string
    {
        $config = $this->get($provider);

        if (! $config || empty($config['api_version_config'])) {
            return $config['api_version_default'] ?? null;
        }

        return config($config['api_version_config'], $config['api_version_default'] ?? null);
    }

    public function catalog(string $provider): array
    {
        return $this->get($provider)['catalog'] ?? [];
    }

    public function defaultModel(string $provider): ?string
    {
        $config = $this->get($provider);

        if (! $config) {
            return null;
        }

        return config($config['default_model_config'], $config['default_model'] ?? null);
    }
}
