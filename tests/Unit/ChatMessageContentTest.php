<?php

use App\Support\ChatMessageContent;

it('normalizes a plain text message into text parts', function () {
    $message = ChatMessageContent::toClientMessage('assistant', 'Bonjour le monde');

    expect($message['role'])->toBe('assistant')
        ->and($message['content'])->toBe('Bonjour le monde')
        ->and($message['parts'])->toBe([
            [
                'type' => 'text',
                'text' => 'Bonjour le monde',
            ],
        ]);
});

it('normalizes image and document parts from provider-style payloads', function () {
    $parts = ChatMessageContent::normalize([
        [
            'type' => 'output_text',
            'text' => 'Voici le livrable.',
        ],
        [
            'type' => 'image_generation_call',
            'result' => base64_encode('fake-image'),
            'mediaType' => 'image/png',
            'revised_prompt' => 'Mockup écran principal',
        ],
        [
            'type' => 'input_file',
            'file_id' => 'file_123',
            'filename' => 'specifications.pdf',
            'mime_type' => 'application/pdf',
        ],
    ]);

    expect($parts)->toHaveCount(3)
        ->and($parts[0])->toMatchArray([
            'type' => 'text',
            'text' => 'Voici le livrable.',
        ])
        ->and($parts[1])->toMatchArray([
            'type' => 'image',
            'mediaType' => 'image/png',
            'alt' => 'Mockup écran principal',
        ])
        ->and($parts[1]['dataUrl'])->toStartWith('data:image/png;base64,')
        ->and($parts[2])->toMatchArray([
            'type' => 'file',
            'filename' => 'specifications.pdf',
            'mediaType' => 'application/pdf',
            'fileId' => 'file_123',
        ]);

    expect(ChatMessageContent::toPlainText($parts))
        ->toContain('Voici le livrable.')
        ->toContain('[Image: Mockup écran principal]')
        ->toContain('[Document: specifications.pdf]');
});
