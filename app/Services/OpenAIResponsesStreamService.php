<?php

namespace App\Services;

use App\Models\UserAIProviderAccount;
use Illuminate\Support\Facades\Log;

class OpenAIResponsesStreamService
{
    private string $eventName = 'message';

    private string $buffer = '';

    private string $responseBody = '';

    public function __construct(private readonly OpenAIAccountService $accounts) {}

    public function stream(UserAIProviderAccount $account, array $payload, callable $onText, callable $onDone): void
    {
        $requestPayload = $this->buildPayload($payload);
        $this->buffer = '';
        $this->responseBody = '';
        $this->eventName = 'message';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->accounts->baseUrl().'/responses',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestPayload),
            CURLOPT_HTTPHEADER => $this->curlHeaders($account),
            CURLOPT_WRITEFUNCTION => function ($ch, string $data) use ($onText, $onDone) {
                if (strlen($this->responseBody) < 65536) {
                    $this->responseBody .= substr($data, 0, 65536 - strlen($this->responseBody));
                }

                return $this->handleData($data, $onText, $onDone);
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
            throw new \RuntimeException($error ?: 'OpenAI streaming connection failed.');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException('OpenAI streaming HTTP error: '.$httpCode.$this->formatErrorMessage($this->responseBody));
        }
    }

    private function buildPayload(array $payload): array
    {
        $model = (string) $payload['model'];
        $request = [
            'model' => $model,
            'input' => $this->messagesToInput($payload['messages'] ?? []),
            'stream' => true,
            'max_output_tokens' => (int) ($payload['maxTokens'] ?? 2048),
        ];

        if (! $this->isReasoningModel($model) && isset($payload['temperature'])) {
            $request['temperature'] = (float) $payload['temperature'];
        }

        $reasoningEffort = $this->reasoningEffortForModel($model, $payload['reasoningEffort'] ?? null);
        if ($reasoningEffort !== null) {
            $request['reasoning'] = ['effort' => $reasoningEffort];
        }

        return $request;
    }

    private function messagesToInput(array $messages): array
    {
        return array_map(function (array $message) {
            $role = $message['role'] ?? 'user';

            return [
                'role' => in_array($role, ['user', 'assistant', 'system', 'developer'], true) ? $role : 'user',
                'content' => (string) ($message['content'] ?? ''),
            ];
        }, $messages);
    }

    private function isReasoningModel(string $model): bool
    {
        return str_starts_with($model, 'gpt-5')
            || preg_match('/^o[134]($|-)/', $model) === 1;
    }

    private function reasoningEffortForModel(string $model, mixed $effort): ?string
    {
        if (! $this->isReasoningModel($model)) {
            return null;
        }

        $effort = is_string($effort) ? strtolower(trim($effort)) : '';

        $allowed = match (true) {
            str_starts_with($model, 'gpt-5.2-pro') => ['medium', 'high', 'xhigh'],
            str_starts_with($model, 'gpt-5.2') => ['none', 'low', 'medium', 'high', 'xhigh'],
            str_starts_with($model, 'gpt-5.1') => ['none', 'low', 'medium', 'high'],
            str_starts_with($model, 'gpt-5') => ['minimal', 'low', 'medium', 'high'],
            default => ['low', 'medium', 'high'],
        };

        return in_array($effort, $allowed, true) ? $effort : 'medium';
    }

    private function handleData(string $data, callable $onText, callable $onDone): int
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
                    $onDone([]);

                    continue;
                }

                $event = json_decode($raw, true);
                if (! is_array($event)) {
                    continue;
                }

                $type = $event['type'] ?? $this->eventName;

                if (in_array($type, ['response.output_text.delta', 'response.refusal.delta'], true)) {
                    $delta = (string) ($event['delta'] ?? '');
                    if ($delta !== '') {
                        $onText($delta);
                    }
                }

                if (in_array($type, ['response.completed', 'response.incomplete'], true)) {
                    $onDone($event);
                }

                if ($type === 'response.failed') {
                    $message = $event['response']['error']['message'] ?? $event['error']['message'] ?? 'OpenAI response failed.';
                    Log::warning('OpenAI response failed', ['message' => $message]);
                    throw new \RuntimeException($message);
                }
            }
        }

        if (ob_get_level()) {
            ob_flush();
        }
        flush();

        return strlen($data);
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

    private function curlHeaders(UserAIProviderAccount $account): array
    {
        return collect($this->accounts->headers($account))
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->values()
            ->all();
    }
}
