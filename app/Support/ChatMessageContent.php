<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ChatMessageContent
{
    /**
     * Normalise un message vers un tableau de parts internes.
     */
    public static function normalize(string|array|null $content = null, ?array $parts = null): array
    {
        $source = $parts;

        if ($source === null && is_array($content)) {
            $source = $content;
        }

        if ($source === null) {
            return self::fromText(is_string($content) ? $content : '');
        }

        $normalized = [];

        foreach ($source as $part) {
            if (is_string($part)) {
                $text = $part;

                if (trim($text) !== '') {
                    $normalized[] = ['type' => 'text', 'text' => $text];
                }

                continue;
            }

            if (! is_array($part)) {
                continue;
            }

            foreach (self::normalizePart($part) as $normalizedPart) {
                $normalized[] = $normalizedPart;
            }
        }

        if ($normalized === []) {
            return self::fromText(is_string($content) ? $content : '');
        }

        return array_values($normalized);
    }

    public static function fromText(string $text): array
    {
        return $text === ''
            ? []
            : [['type' => 'text', 'text' => $text]];
    }

    public static function toPlainText(?array $parts, ?string $fallback = null): string
    {
        $normalized = self::normalize($fallback, $parts);

        if ($normalized === []) {
            return trim((string) ($fallback ?? ''));
        }

        $segments = [];

        foreach ($normalized as $part) {
            if (($part['type'] ?? null) === 'text') {
                $text = trim((string) ($part['text'] ?? ''));

                if ($text !== '') {
                    $segments[] = $text;
                }

                continue;
            }

            if (($part['type'] ?? null) === 'image') {
                $label = trim((string) ($part['alt'] ?? $part['filename'] ?? 'image'));
                $segments[] = "[Image: {$label}]";

                continue;
            }

            if (($part['type'] ?? null) === 'file') {
                $label = trim((string) ($part['title'] ?? $part['filename'] ?? $part['fileId'] ?? 'document'));
                $segments[] = "[Document: {$label}]";
            }
        }

        $text = trim(implode("\n\n", $segments));

        if ($text !== '') {
            return $text;
        }

        return trim((string) ($fallback ?? ''));
    }

    public static function toClientParts(?array $parts, ?string $fallback = null): array
    {
        return array_map(function (array $part) {
            $url = self::stringValue($part['url'] ?? null);

            if (! $url && ! empty($part['disk']) && ! empty($part['path'])) {
                $url = Storage::disk((string) $part['disk'])->url((string) $part['path']);
            }

            if (! $url && ! empty($part['dataUrl'])) {
                $url = (string) $part['dataUrl'];
            }

            if ($url) {
                $part['url'] = $url;
            }

            return $part;
        }, self::normalize($fallback, $parts));
    }

    public static function toClientMessage(string $role, ?string $content = null, ?array $parts = null, array $extra = []): array
    {
        $clientParts = self::toClientParts($parts, $content);
        $plainText = self::toPlainText($parts, $content);

        return [
            'role' => $role,
            'content' => $plainText,
            'parts' => $clientParts,
            ...$extra,
        ];
    }

    private static function normalizePart(array $part): array
    {
        $type = strtolower((string) ($part['type'] ?? self::inferType($part)));

        if ($type === 'message' && is_array($part['content'] ?? null)) {
            return self::normalize($part['content']);
        }

        if (in_array($type, ['text', 'input_text', 'output_text'], true)) {
            $text = self::stringValue($part['text'] ?? $part['content'] ?? $part['value'] ?? null);

            return $text !== null && $text !== ''
                ? [['type' => 'text', 'text' => $text]]
                : [];
        }

        if (in_array($type, ['image', 'input_image', 'output_image', 'image_generation_call'], true)) {
            $imagePart = self::normalizeImagePart($part);

            return $imagePart ? [$imagePart] : [];
        }

        if (in_array($type, ['file', 'input_file', 'document'], true)) {
            $filePart = self::normalizeFilePart($part);

            return $filePart ? [$filePart] : [];
        }

        if (is_array($part['content'] ?? null)) {
            return self::normalize($part['content']);
        }

        $text = self::stringValue($part['content'] ?? $part['text'] ?? null);

        return $text !== null && $text !== ''
            ? [['type' => 'text', 'text' => $text]]
            : [];
    }

    private static function normalizeImagePart(array $part): ?array
    {
        $mediaType = self::stringValue($part['mediaType'] ?? $part['mimeType'] ?? $part['mime_type'] ?? null) ?? 'image/png';
        $url = self::stringValue($part['url'] ?? $part['image_url'] ?? $part['imageUrl'] ?? null);
        $dataUrl = self::normalizeDataUrl(
            self::stringValue($part['dataUrl'] ?? $part['data_url'] ?? $part['b64_json'] ?? $part['result'] ?? $part['data'] ?? null),
            $mediaType
        );

        if (! $url && is_array($part['image_url'] ?? null)) {
            $url = self::stringValue($part['image_url']['url'] ?? null);
        }

        if (! $url && ! $dataUrl && empty($part['path'])) {
            return null;
        }

        return array_filter([
            'type' => 'image',
            'url' => $url,
            'dataUrl' => $dataUrl,
            'disk' => self::stringValue($part['disk'] ?? null),
            'path' => self::stringValue($part['path'] ?? null),
            'filename' => self::stringValue($part['filename'] ?? $part['name'] ?? null),
            'mediaType' => $mediaType,
            'alt' => self::stringValue($part['alt'] ?? $part['caption'] ?? $part['revised_prompt'] ?? null),
            'size' => self::intValue($part['size'] ?? $part['bytes'] ?? null),
            'fileId' => self::stringValue($part['file_id'] ?? $part['fileId'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function normalizeFilePart(array $part): ?array
    {
        $mediaType = self::stringValue($part['mediaType'] ?? $part['mimeType'] ?? $part['mime_type'] ?? null);
        $url = self::stringValue($part['url'] ?? $part['file_url'] ?? $part['fileUrl'] ?? null);
        $dataUrl = self::normalizeDataUrl(
            self::stringValue($part['dataUrl'] ?? $part['data_url'] ?? $part['data'] ?? null),
            $mediaType
        );

        if (! $url && is_array($part['file_url'] ?? null)) {
            $url = self::stringValue($part['file_url']['url'] ?? null);
        }

        if (! $url && ! $dataUrl && empty($part['path']) && empty($part['file_id']) && empty($part['fileId'])) {
            return null;
        }

        return array_filter([
            'type' => 'file',
            'url' => $url,
            'dataUrl' => $dataUrl,
            'disk' => self::stringValue($part['disk'] ?? null),
            'path' => self::stringValue($part['path'] ?? null),
            'filename' => self::stringValue($part['filename'] ?? $part['name'] ?? $part['title'] ?? null),
            'title' => self::stringValue($part['title'] ?? null),
            'mediaType' => $mediaType,
            'size' => self::intValue($part['size'] ?? $part['bytes'] ?? null),
            'fileId' => self::stringValue($part['file_id'] ?? $part['fileId'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function inferType(array $part): string
    {
        if (array_key_exists('b64_json', $part) || array_key_exists('image_url', $part) || array_key_exists('imageUrl', $part)) {
            return 'image';
        }

        if (array_key_exists('file_id', $part) || array_key_exists('fileId', $part) || array_key_exists('file_url', $part) || array_key_exists('fileUrl', $part)) {
            return 'file';
        }

        return 'text';
    }

    private static function normalizeDataUrl(?string $value, ?string $mediaType): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:')) {
            return $value;
        }

        if ($mediaType === null || $mediaType === '') {
            return null;
        }

        return "data:{$mediaType};base64,{$value}";
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function intValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
