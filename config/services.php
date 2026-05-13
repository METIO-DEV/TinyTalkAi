<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ollama API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour l'API Ollama. Dans un environnement Docker (Laravel Sail),
    | nous utilisons host.docker.internal pour accéder à la machine hôte.
    | En local, nous utilisons localhost.
    |
    */
    'ollama' => [
        'host' => env('OLLAMA_HOST', 'host.docker.internal'),
        'port' => env('OLLAMA_PORT', '11434'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    ],

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-5-mini'),
    ],

    'anthropic' => [
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-5-20250929'),
    ],

    'mistral' => [
        'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
        'default_model' => env('MISTRAL_DEFAULT_MODEL', 'mistral-large-latest'),
    ],

    'groq' => [
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'default_model' => env('GROQ_DEFAULT_MODEL', 'llama-3.3-70b-versatile'),
    ],

    'openrouter' => [
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'default_model' => env('OPENROUTER_DEFAULT_MODEL', 'anthropic/claude-sonnet-4.5'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Qdrant API Configuration
    |--------------------------------------------------------------------------
    |
    | Qdrant stores document embeddings for retrieval augmented generation.
    |
    */
    'qdrant' => [
        'host' => env('QDRANT_HOST', 'host.docker.internal'),
        'port' => env('QDRANT_PORT', '6333'),
        'collection' => env('QDRANT_COLLECTION', 'docs'),
    ],

];
