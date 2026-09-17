<?php

use App\Services\ChatbotService;
use Illuminate\Support\Facades\Http;

test('the chatbot page loads', function () {
    $this->get(route('chatbot.index'))
        ->assertStatus(200);
});

test('a message is required to talk to the chatbot', function () {
    $this->postJson(route('chatbot.respond'), [])
        ->assertStatus(422);
});

test('the chatbot answers using groq', function () {
    config(['services.groq.api_key' => 'test-key']);

    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [[
                'message' => ['content' => '{"needs_database":false,"sql":""}'],
            ]],
        ]),
    ]);

    Http::fake(function ($request) {
        $payload = $request->data();
        $isDecision = str_contains($payload['messages'][0]['content'] ?? '', 'decide whether');

        if ($isDecision) {
            return Http::response([
                'choices' => [[
                    'message' => ['content' => '{"needs_database":false,"sql":""}'],
                ]],
            ]);
        }

        return Http::response([
            'choices' => [[
                'message' => ['content' => 'Hello! How can I help?'],
            ]],
        ]);
    });

    $result = app(ChatbotService::class)->respond('hello');

    expect($result['content'])->toBe('Hello! How can I help?');
});

test('the chatbot refuses queries outside the allowed tables', function () {
    config(['services.groq.api_key' => 'test-key']);

    Http::fake(function ($request) {
        $payload = $request->data();
        $isDecision = str_contains($payload['messages'][0]['content'] ?? '', 'decide whether');

        if ($isDecision) {
            return Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'needs_database' => true,
                        'sql' => 'select * from users',
                    ])],
                ]],
            ]);
        }

        return Http::response([
            'choices' => [[
                'message' => ['content' => 'Should not get here.'],
            ]],
        ]);
    });

    expect(fn () => app(ChatbotService::class)->respond('show me all users'))
        ->toThrow(RuntimeException::class);
});
