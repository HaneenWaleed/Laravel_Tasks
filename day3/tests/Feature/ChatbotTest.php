<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('chatbot page is available', function () {
    $this->get(route('chatbot.index'))
        ->assertOk()
        ->assertSee('Store Assistant', false);
});

test('chatbot requires a message', function () {
    $this->postJson(route('chatbot.ask'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

test('chatbot returns store overview from database', function () {
    $category = Category::factory()->create(['name' => 'Electronics']);

    Product::factory()->create([
        'name' => 'Laptop',
        'price' => 999.99,
        'quantity' => 3,
        'category_id' => $category->id,
    ]);

    $response = $this->postJson(route('chatbot.ask'), [
        'message' => 'Give me an overview',
    ]);

    $response->assertOk()
        ->assertJsonPath('intent', 'overview')
        ->assertJsonFragment(['intent' => 'overview']);

    expect($response->json('reply'))
        ->toContain('Products: 1')
        ->toContain('Categories: 1');
});

test('chatbot answers product price questions from data', function () {
    $category = Category::factory()->create(['name' => 'Electronics']);

    Product::factory()->create([
        'name' => 'Laptop',
        'description' => 'Powerful notebook',
        'price' => 1299.50,
        'quantity' => 4,
        'category_id' => $category->id,
    ]);

    $response = $this->postJson(route('chatbot.ask'), [
        'message' => 'What is the price of Laptop?',
    ]);

    $response->assertOk();

    expect($response->json('reply'))
        ->toContain('Laptop')
        ->toContain('1,299.50');
});

test('chatbot lists products in a category', function () {
    $electronics = Category::factory()->create(['name' => 'Electronics']);
    $books = Category::factory()->create(['name' => 'Books']);

    Product::factory()->create([
        'name' => 'Phone',
        'price' => 500,
        'quantity' => 10,
        'category_id' => $electronics->id,
    ]);

    Product::factory()->create([
        'name' => 'Novel',
        'price' => 20,
        'quantity' => 8,
        'category_id' => $books->id,
    ]);

    $response = $this->postJson(route('chatbot.ask'), [
        'message' => 'Show products in Electronics',
    ]);

    $response->assertOk()
        ->assertJsonPath('intent', 'category_products');

    expect($response->json('reply'))
        ->toContain('Phone')
        ->not->toContain('Novel');
});

test('chatbot uses gemini when api key is configured', function () {
    config([
        'services.gemini.key' => 'test-gemini-key',
        'services.gemini.model' => 'gemini-1.5-flash',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Laptop costs $999.99 according to your store data.'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $category = Category::factory()->create(['name' => 'Electronics']);

    Product::factory()->create([
        'name' => 'Laptop',
        'price' => 999.99,
        'quantity' => 2,
        'category_id' => $category->id,
    ]);

    $response = $this->postJson(route('chatbot.ask'), [
        'message' => 'How much is the Laptop?',
    ]);

    $response->assertOk()
        ->assertJsonPath('intent', 'gemini')
        ->assertJsonPath('reply', 'Laptop costs $999.99 according to your store data.');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains((string) $request->url(), 'key=test-gemini-key');
    });
});

test('chatbot uses openai when api key is configured', function () {
    config([
        'services.openai.key' => 'test-openai-key',
        'services.openai.model' => 'gpt-4o-mini',
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Laptop costs $999.99 according to your store data.',
                    ],
                ],
            ],
        ], 200),
    ]);

    $category = Category::factory()->create(['name' => 'Electronics']);

    Product::factory()->create([
        'name' => 'Laptop',
        'price' => 999.99,
        'quantity' => 2,
        'category_id' => $category->id,
    ]);

    $response = $this->postJson(route('chatbot.ask'), [
        'message' => 'How much is the Laptop?',
    ]);

    $response->assertOk()
        ->assertJsonPath('intent', 'openai')
        ->assertJsonPath('reply', 'Laptop costs $999.99 according to your store data.');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-openai-key');
    });
});
