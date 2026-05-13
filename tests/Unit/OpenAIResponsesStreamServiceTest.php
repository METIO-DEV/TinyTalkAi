<?php

use App\Services\OpenAIAccountService;
use App\Services\OpenAIResponsesStreamService;

function buildOpenAIResponsesPayload(array $payload): array
{
    $service = new OpenAIResponsesStreamService(new OpenAIAccountService);
    $method = new ReflectionMethod($service, 'buildPayload');
    $method->setAccessible(true);

    return $method->invoke($service, $payload);
}

it('omits temperature for GPT-5 reasoning models', function () {
    $payload = buildOpenAIResponsesPayload([
        'model' => 'gpt-5-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'Réponds en français.'],
            ['role' => 'user', 'content' => 'Bonjour'],
        ],
        'temperature' => 0.7,
        'maxTokens' => 2048,
        'reasoningEffort' => 'medium',
    ]);

    expect($payload)->not->toHaveKey('temperature')
        ->and($payload['reasoning'])->toBe(['effort' => 'medium']);
});

it('normalizes invalid reasoning efforts for older GPT-5 models', function () {
    $payload = buildOpenAIResponsesPayload([
        'model' => 'gpt-5-mini',
        'messages' => [
            ['role' => 'user', 'content' => 'Bonjour'],
        ],
        'reasoningEffort' => 'none',
    ]);

    expect($payload['reasoning'])->toBe(['effort' => 'medium']);
});

it('keeps temperature for non reasoning models', function () {
    $payload = buildOpenAIResponsesPayload([
        'model' => 'gpt-4.1-mini',
        'messages' => [
            ['role' => 'user', 'content' => 'Bonjour'],
        ],
        'temperature' => 0.3,
        'reasoningEffort' => 'medium',
    ]);

    expect($payload['temperature'])->toBe(0.3)
        ->and($payload)->not->toHaveKey('reasoning');
});
