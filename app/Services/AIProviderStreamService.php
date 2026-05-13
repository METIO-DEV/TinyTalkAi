<?php

namespace App\Services;

use App\Models\UserAIProviderAccount;
use Illuminate\Support\Facades\Log;

class AIProviderStreamService
{
    private string $eventName = 'message';

    private string $buffer = '';

    private string $responseBody = '';

    private array $lastAnthropicUsage = [];

    public function __construct(
        private readonly AIProviderAccountService $accounts,
        private readonly AIProviderConfigService $providers,
        private readonly OpenAIResponsesStreamService $openAIStream,
    ) {}

    public function stream(UserAIProviderAccount $account, array $payload, callable $onText, callable $onDone): void
    {
        $driver = $this->providers->get($account->provider)['stream_driver'] ?? 'openai_chat';

        if ($driver === 'openai_responses') {
            $this->openAIStream->stream($account, $payload, $onText, $onDone);

            return;
        }

        if ($driver === 'anthropic_messages') {
            $this->streamAnthropic($account, $payload, $onText, $onDone);

            return;
        }

        $this->streamChatCompletions($account, $payload, $onText, $onDone);
    }

    public function requestSummary(UserAIProviderAccount $account, array $messages, string $model): array
    {
        $driver = $this->providers->get($account->provider)['stream_driver'] ?? 'openai_chat';

        if ($driver === 'openai_responses') {
            $response = \Illuminate\Support\Facades\Http::withHeaders($this->accounts->headers($account))
                ->timeout(300)
                ->post($this->providers->baseUrl($account->provider).'/responses', [
                    'model' => $model,
                    'input' => $messages,
                    'max_output_tokens' => 300,
                ]);

            return [
                'successful' => $response->successful(),
                'content' => $response->json('output_text') ?: $this->extractOpenAIText($response->json('output', [])),
                'tokens' => (int) ($response->json('usage.total_tokens') ?? $response->json('usage.output_tokens') ?? 0),
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        }

        if ($driver === 'anthropic_messages') {
            $payload = $this->buildAnthropicPayload([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.3,
                'maxTokens' => 300,
            ], false);

            $response = \Illuminate\Support\Facades\Http::withHeaders($this->accounts->headers($account))
                ->timeout(300)
                ->post($this->providers->baseUrl($account->provider).'/messages', $payload);

            return [
                'successful' => $response->successful(),
                'content' => $this->extractAnthropicText($response->json('content', [])),
                'tokens' => (int) (($response->json('usage.input_tokens') ?? 0) + ($response->json('usage.output_tokens') ?? 0)),
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders($this->accounts->headers($account))
            ->timeout(300)
            ->post($this->providers->baseUrl($account->provider).'/chat/completions', [
                'model' => $model,
                'messages' => $this->normalizeChatMessages($messages),
                'temperature' => 0.3,
                'max_tokens' => 300,
                'stream' => false,
            ]);

        return [
            'successful' => $response->successful(),
            'content' => $response->json('choices.0.message.content'),
            'tokens' => (int) ($response->json('usage.total_tokens') ?? 0),
            'status' => $response->status(),
            'body' => $response->body(),
        ];
    }

    private function streamChatCompletions(UserAIProviderAccount $account, array $payload, callable $onText, callable $onDone): void
    {
        $requestPayload = [
            'model' => (string) $payload['model'],
            'messages' => $this->normalizeChatMessages($payload['messages'] ?? []),
            'stream' => true,
            'max_tokens' => (int) ($payload['maxTokens'] ?? 2048),
        ];

        if (isset($payload['temperature'])) {
            $requestPayload['temperature'] = (float) $payload['temperature'];
        }

        $this->streamCurl(
            $this->providers->baseUrl($account->provider).'/chat/completions',
            $account,
            $requestPayload,
            fn (string $data) => $this->handleChatCompletionsData($data, $onText, $onDone),
            $account->provider,
        );
    }

    private function streamAnthropic(UserAIProviderAccount $account, array $payload, callable $onText, callable $onDone): void
    {
        $this->lastAnthropicUsage = [];

        $this->streamCurl(
            $this->providers->baseUrl($account->provider).'/messages',
            $account,
            $this->buildAnthropicPayload($payload, true),
            fn (string $data) => $this->handleAnthropicData($data, $onText, $onDone),
            $account->provider,
        );
    }

    private function streamCurl(string $url, UserAIProviderAccount $account, array $payload, callable $onData, string $provider): void
    {
        $this->buffer = '';
        $this->responseBody = '';
        $this->eventName = 'message';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $this->accounts->curlHeaders($account),
            CURLOPT_WRITEFUNCTION => function ($ch, string $data) use ($onData) {
                if (strlen($this->responseBody) < 65536) {
                    $this->responseBody .= substr($data, 0, 65536 - strlen($this->responseBody));
                }

                return $onData($data);
            },
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_BUFFERSIZE => 128,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false || $error !== '') {
            throw new \RuntimeException($error ?: "{$provider} streaming connection failed.");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("{$provider} streaming HTTP error: {$httpCode}".$this->formatErrorMessage($this->responseBody));
        }
    }

    private function handleChatCompletionsData(string $data, callable $onText, callable $onDone): int
    {
        $this->eachSseEvent($data, function (array $event) use ($onText, $onDone) {
            $choice = $event['choices'][0] ?? [];
            $delta = $choice['delta']['content'] ?? null;

            if (is_string($delta) && $delta !== '') {
                $onText($delta);
            }

            if (($choice['finish_reason'] ?? null) !== null || isset($event['usage'])) {
                $onDone($event);
            }
        });

        return $this->flushAndLength($data);
    }

    private function handleAnthropicData(string $data, callable $onText, callable $onDone): int
    {
        $this->eachSseEvent($data, function (array $event) use ($onText, $onDone) {
            $type = $event['type'] ?? $this->eventName;

            if ($type === 'message_start') {
                $this->lastAnthropicUsage = $event['message']['usage'] ?? [];
            }

            if ($type === 'message_delta') {
                $this->lastAnthropicUsage = [
                    ...$this->lastAnthropicUsage,
                    ...($event['usage'] ?? []),
                ];
            }

            if ($type === 'content_block_delta' && ($event['delta']['type'] ?? null) === 'text_delta') {
                $delta = (string) ($event['delta']['text'] ?? '');
                if ($delta !== '') {
                    $onText($delta);
                }
            }

            if ($type === 'message_stop') {
                $onDone([
                    'provider' => 'anthropic',
                    'usage' => $this->lastAnthropicUsage,
                    'content' => [
                        ['type' => 'text', 'text' => ''],
                    ],
                ]);
            }
        });

        return $this->flushAndLength($data);
    }

    private function eachSseEvent(string $data, callable $handler): void
    {
        $this->buffer .= $data;
        $events = preg_split("/\r?\n\r?\n/", $this->buffer);
        $this->buffer = array_pop($events) ?? '';

        foreach ($events as $rawEvent) {
            foreach (preg_split("/\r?\n/", $rawEvent) as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                if (str_starts_with($line, 'event:')) {
                    $this->eventName = trim(substr($line, 6));

                    continue;
                }

                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $raw = trim(substr($line, 5));
                if ($raw === '[DONE]') {
                    $handler(['done' => true]);

                    continue;
                }

                $event = json_decode($raw, true);
                if (! is_array($event)) {
                    continue;
                }

                if (($event['type'] ?? null) === 'error') {
                    $message = $event['error']['message'] ?? 'Provider response failed.';
                    Log::warning('AI provider stream failed', ['message' => $message]);
                    throw new \RuntimeException($message);
                }

                $handler($event);
            }
        }
    }

    private function normalizeChatMessages(array $messages): array
    {
        return array_map(function (array $message) {
            $role = $message['role'] ?? 'user';

            return [
                'role' => in_array($role, ['user', 'assistant', 'system'], true) ? $role : 'user',
                'content' => (string) ($message['content'] ?? ''),
            ];
        }, $messages);
    }

    private function buildAnthropicPayload(array $payload, bool $stream): array
    {
        $system = [];
        $messages = [];

        foreach (($payload['messages'] ?? []) as $message) {
            $role = $message['role'] ?? 'user';
            $content = (string) ($message['content'] ?? '');

            if (in_array($role, ['system', 'developer'], true)) {
                $system[] = $content;

                continue;
            }

            $messages[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        $request = [
            'model' => (string) $payload['model'],
            'messages' => $messages,
            'stream' => $stream,
            'max_tokens' => (int) ($payload['maxTokens'] ?? 2048),
        ];

        if ($system !== []) {
            $request['system'] = implode("\n\n", array_filter($system));
        }

        if (isset($payload['temperature'])) {
            $request['temperature'] = (float) $payload['temperature'];
        }

        return $request;
    }

    private function extractOpenAIText(array $output): ?string
    {
        $texts = [];

        foreach ($output as $item) {
            foreach (($item['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'output_text' && isset($part['text'])) {
                    $texts[] = $part['text'];
                }
            }
        }

        $text = trim(implode("\n", $texts));

        return $text !== '' ? $text : null;
    }

    private function extractAnthropicText(array $content): ?string
    {
        $text = collect($content)
            ->where('type', 'text')
            ->pluck('text')
            ->filter()
            ->implode("\n");

        return $text !== '' ? $text : null;
    }

    private function formatErrorMessage(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        $json = json_decode($body, true);
        if (is_array($json)) {
            $message = $json['error']['message'] ?? $json['message'] ?? null;
            if (is_string($message) && trim($message) !== '') {
                return ' - '.$message;
            }
        }

        return ' - '.mb_strimwidth($body, 0, 500, '...');
    }

    private function flushAndLength(string $data): int
    {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();

        return strlen($data);
    }
}
