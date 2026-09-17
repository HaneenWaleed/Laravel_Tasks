<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ChatbotService
{
    /**
     * Tables (and the only columns) the chatbot is allowed to read.
     * Sensitive columns like password / remember_token are intentionally left out.
     */
    private const TABLES = [
        'categories' => ['id', 'name', 'description', 'created_at', 'updated_at'],
        'products' => ['id', 'name', 'description', 'price', 'quantity', 'category_id', 'created_at', 'updated_at'],
        'orders' => ['id', 'user_id', 'category_id', 'created_at', 'updated_at'],
        'order_items' => ['id', 'order_id', 'product_id', 'quantity', 'price', 'created_at', 'updated_at'],
        'users' => ['id', 'name', 'email', 'role', 'created_at', 'updated_at'],
    ];

    public function respond(string $message): array
    {
        $databaseContext = $this->answerFromDatabase($message);

        return [
            'content' => $this->generateText($message, $databaseContext),
        ];
    }

    private function generateText(string $message, ?array $databaseContext): string
    {
        $context = $databaseContext === null
            ? 'No database query was needed for this request.'
            : json_encode($databaseContext, JSON_THROW_ON_ERROR);

        $response = $this->client()->post('/chat/completions', [
            'model' => config('services.groq.model'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are a helpful assistant for a store management application (categories, products, orders, order items). Answer using the database context when it is provided. Never invent database facts and never reveal passwords, tokens, or other sensitive data.\n\nDatabase context:\n{$context}",
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ],
            ],
            'temperature' => 0.3,
        ])->throw()->json();

        return $this->extractMessage($response);
    }

    private function answerFromDatabase(string $message): ?array
    {
        $schema = json_encode(self::TABLES, JSON_THROW_ON_ERROR);

        $response = $this->client()->post('/chat/completions', [
            'model' => config('services.groq.model'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You decide whether a user request needs data from a store database. Reply with ONLY a raw JSON object (no markdown, no code fences) with keys "needs_database" (boolean) and "sql" (string). The SQL must be a single SELECT statement, use only the supplied tables and columns, contain no comments and no semicolons. If no database data is needed, reply {"needs_database":false,"sql":""}.',
                ],
                [
                    'role' => 'user',
                    'content' => "Schema: {$schema}\nRequest: {$message}",
                ],
            ],
            'temperature' => 0,
        ])->throw()->json();

        $decision = json_decode($this->cleanJson($this->extractMessage($response)), true);

        if (! is_array($decision) || ! ($decision['needs_database'] ?? false)) {
            return null;
        }

        $sql = $decision['sql'] ?? null;

        if (! is_string($sql) || $sql === '') {
            return null;
        }

        $this->validateSql($sql);

        return [
            'rows' => DB::select($sql.' LIMIT 100'),
        ];
    }

    private function validateSql(string $sql): void
    {
        $normalized = strtolower(trim($sql));

        if (! Str::startsWith($normalized, 'select ') || str_contains($normalized, ';') || str_contains($normalized, '--') || str_contains($normalized, '/*')) {
            throw new RuntimeException('Only read-only SELECT queries are allowed.');
        }

        if (preg_match('/\b(insert|update|delete|drop|alter|create|replace|attach|pragma|union)\b/i', $normalized)) {
            throw new RuntimeException('Only read-only SELECT queries are allowed.');
        }

        foreach (preg_match_all('/\b(?:from|join)\s+([a-z_][a-z0-9_]*)/i', $normalized, $matches) ? $matches[1] : [] as $table) {
            if (! array_key_exists($table, self::TABLES)) {
                throw new RuntimeException("Querying the '{$table}' table is not allowed.");
            }
        }

        if (preg_match('/\b(password|remember_token|token|secret)\b/i', $normalized)) {
            throw new RuntimeException('Sensitive columns cannot be queried.');
        }

        if (preg_match('/\busers\b/i', $normalized) && preg_match('/\bselect\s+\*/i', $normalized)) {
            throw new RuntimeException('User queries must select approved columns explicitly.');
        }
    }

    private function client(): PendingRequest
    {
        $apiKey = config('services.groq.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('GROQ_API_KEY is not configured.');
        }

        return Http::baseUrl(config('services.groq.base_url'))
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(60);
    }

    private function extractMessage(array $response): string
    {
        $text = data_get($response, 'choices.0.message.content');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('The chatbot provider returned no response.');
        }

        return $text;
    }

    /**
     * Groq sometimes wraps JSON replies in ```json fences despite instructions not to.
     */
    private function cleanJson(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(json)?/i', '', $text);
        $text = preg_replace('/```$/', '', $text);

        return trim($text);
    }
}
