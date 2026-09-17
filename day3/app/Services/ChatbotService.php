<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\Order_Item;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ChatbotService
{
    /**
     * Answer a user question using OpenAI (when configured) and live project data.
     *
     * @return array{reply: string, intent: string}
     */
    public function reply(string $message): array
    {
        $normalized = Str::of($message)->lower()->trim()->toString();

        if ($normalized === '') {
            return $this->response(
                'Please type a question about products, categories, orders, or store statistics.',
                'empty'
            );
        }

        if ($this->geminiEnabled()) {
            try {
                return $this->replyWithGemini($message);
            } catch (Throwable $exception) {
                Log::warning('Gemini chatbot failed, falling back to OpenAI or local replies.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($this->openAiEnabled()) {
            try {
                return $this->replyWithOpenAi($message);
            } catch (Throwable $exception) {
                Log::warning('OpenAI chatbot failed, falling back to local replies.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $this->replyLocally($normalized);
    }

    public function openAiEnabled(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function geminiEnabled(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function replyWithGemini(string $message): array
    {
        $apiKey = (string) config('services.gemini.key');
        $model = (string) config('services.gemini.model', 'gemini-1.5-flash');

        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(45)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $this->geminiSystemPrompt()],
                    ],
                ],
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $message],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            $apiMessage = $response->json('error.message') ?? 'Gemini request failed.';

            Log::warning('Gemini API error.', [
                'status' => $response->status(),
                'error' => $apiMessage,
            ]);

            return $this->response(
                'I could not reach the AI service right now ('.$apiMessage.'). Please check your Gemini API key and billing, then try again.',
                'gemini_error'
            );
        }

        $reply = trim((string) $response->json('candidates.0.content.parts.0.text'));

        if ($reply === '') {
            return $this->response(
                'The AI returned an empty response. Please try asking again.',
                'gemini_empty'
            );
        }

        return $this->response($reply, 'gemini');
    }

    private function replyWithOpenAi(string $message): array
    {
        $response = Http::withToken((string) config('services.openai.key'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                'temperature' => 0.2,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->openAiSystemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $message,
                    ],
                ],
            ]);

        if ($response->failed()) {
            $apiMessage = $response->json('error.message') ?? 'OpenAI request failed.';

            Log::warning('OpenAI API error.', [
                'status' => $response->status(),
                'error' => $apiMessage,
            ]);

            return $this->response(
                'I could not reach the AI service right now ('.$apiMessage.'). Please check your API key and billing, then try again.',
                'openai_error'
            );
        }

        $reply = trim((string) $response->json('choices.0.message.content'));

        if ($reply === '') {
            return $this->response(
                'The AI returned an empty response. Please try asking again.',
                'openai_empty'
            );
        }

        return $this->response($reply, 'openai');
    }

    private function openAiSystemPrompt(): string
    {
        $context = $this->buildDatabaseContext();

        return <<<PROMPT
You are Store Assistant, a helpful ecommerce chatbot for this Laravel project.
Answer in clear, professional English.
Use ONLY the live store data JSON below. Do not invent products, prices, orders, or users.
If the answer is not in the data, say you could not find it in the store database.
Be concise, use bullet points when listing items, and format money with a \$ sign.

LIVE STORE DATA (JSON):
{$context}
PROMPT;
    }

    private function geminiSystemPrompt(): string
    {
        $context = $this->buildDatabaseContext();

        return <<<PROMPT
You are Store Assistant, a helpful ecommerce chatbot for this Laravel project.
Answer in clear, professional English.
Use ONLY the live store data JSON below. Do not invent products, prices, orders, or users.
If the answer is not in the data, say you could not find it in the store database.
Be concise, use bullet points when listing items, and format money with a \$ sign.

LIVE STORE DATA (JSON):
{$context}
PROMPT;
    }

    private function buildDatabaseContext(): string
    {
        $products = Product::query()
            ->with('category:id,name')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'description', 'price', 'quantity', 'category_id']);

        $categories = Category::query()
            ->withCount('products')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $orders = Order::query()
            ->with([
                'user:id,name',
                'category:id,name',
                'orderItems:id,order_id,product_id,quantity,price',
                'orderItems.product:id,name',
            ])
            ->latest()
            ->limit(30)
            ->get();

        $payload = [
            'stats' => [
                'categories' => Category::query()->count(),
                'products' => Product::query()->count(),
                'orders' => Order::query()->count(),
                'order_items' => Order_Item::query()->count(),
                'users' => User::query()->count(),
                'average_product_price' => round((float) (Product::query()->avg('price') ?? 0), 2),
                'inventory_value' => round((float) Product::query()->selectRaw('COALESCE(SUM(price * quantity), 0) as total')->value('total'), 2),
            ],
            'categories' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'products_count' => $category->products_count,
            ])->values(),
            'products' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => (float) $product->price,
                'quantity' => (int) $product->quantity,
                'category' => $product->category?->name,
            ])->values(),
            'recent_orders' => $orders->map(function (Order $order): array {
                $items = $order->orderItems;
                $total = $items->sum(fn (Order_Item $item): float => (float) $item->price * (int) $item->quantity);

                return [
                    'id' => $order->id,
                    'customer' => $order->user?->name,
                    'category' => $order->category?->name,
                    'created_at' => optional($order->created_at)?->toDateTimeString(),
                    'total' => round((float) $total, 2),
                    'items' => $items->map(fn (Order_Item $item): array => [
                        'product' => $item->product?->name,
                        'quantity' => (int) $item->quantity,
                        'price' => (float) $item->price,
                    ])->values(),
                ];
            })->values(),
        ];

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Rule-based fallback when OpenAI is not configured or unavailable.
     *
     * @return array{reply: string, intent: string}
     */
    private function replyLocally(string $normalized): array
    {
        if ($this->matches($normalized, ['hello', 'hi', 'hey', 'good morning', 'good evening', 'greetings'])) {
            return $this->response(
                "Hello! I'm your store assistant. Ask me about products, categories, orders, stock, prices, or overall statistics.",
                'greeting'
            );
        }

        if ($this->matches($normalized, ['help', 'what can you do', 'commands', 'how to use'])) {
            return $this->response($this->helpText(), 'help');
        }

        if ($this->matches($normalized, ['overview', 'summary', 'dashboard', 'statistics', 'stats', 'how many', 'total'])) {
            if ($this->matches($normalized, ['product'])) {
                return $this->productStats($normalized);
            }

            if ($this->matches($normalized, ['categor'])) {
                return $this->categoryStats();
            }

            if ($this->matches($normalized, ['order item', 'order-item', 'line item'])) {
                return $this->orderItemStats();
            }

            if ($this->matches($normalized, ['order'])) {
                return $this->orderStats();
            }

            if ($this->matches($normalized, ['user', 'customer'])) {
                return $this->userStats();
            }

            return $this->overview();
        }

        if ($this->matches($normalized, ['out of stock', 'no stock', 'zero stock', 'unavailable'])) {
            return $this->outOfStockProducts();
        }

        if ($this->matches($normalized, ['low stock', 'running low', 'few left'])) {
            return $this->lowStockProducts();
        }

        if ($this->matches($normalized, ['cheapest', 'lowest price', 'least expensive'])) {
            return $this->cheapestProducts();
        }

        if ($this->matches($normalized, ['most expensive', 'highest price', 'pricey', 'costliest'])) {
            return $this->mostExpensiveProducts();
        }

        if ($this->matches($normalized, ['products in', 'products under', 'in category', 'under category'])) {
            $categoryReply = $this->productsInCategory($normalized);
            if ($categoryReply !== null) {
                return $categoryReply;
            }
        }

        if ($this->matches($normalized, ['list products', 'show products', 'all products', 'products list'])) {
            return $this->listProducts();
        }

        if ($this->matches($normalized, ['list categor', 'show categor', 'all categor', 'categories'])) {
            return $this->listCategories();
        }

        if ($this->matches($normalized, ['list order', 'show order', 'all order', 'recent order'])) {
            return $this->listOrders();
        }

        if (preg_match('/\border\s*(?:number|#|id)?\s*(\d+)\b/', $normalized, $matches) === 1) {
            return $this->orderDetails((int) $matches[1]);
        }

        if (preg_match('/\bproduct\s*(?:number|#|id)?\s*(\d+)\b/', $normalized, $matches) === 1) {
            return $this->productById((int) $matches[1]);
        }

        if ($this->matches($normalized, ['price of', 'how much', 'cost of', 'price for'])) {
            return $this->productPriceLookup($normalized);
        }

        if ($this->matches($normalized, ['category'])) {
            $categoryReply = $this->productsInCategory($normalized);
            if ($categoryReply !== null) {
                return $categoryReply;
            }
        }

        if ($this->matches($normalized, ['find', 'search', 'look for', 'about product', 'tell me about', 'details of', 'info about'])) {
            $searchReply = $this->searchProducts($normalized);
            if ($searchReply !== null) {
                return $searchReply;
            }
        }

        $directProduct = $this->searchProducts($normalized);
        if ($directProduct !== null) {
            return $directProduct;
        }

        return $this->response(
            "I couldn't match that question. Try asking about products, categories, orders, stock, prices, or say \"help\".",
            'fallback'
        );
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function response(string $reply, string $intent): array
    {
        return [
            'reply' => $reply,
            'intent' => $intent,
        ];
    }

    /**
     * @param  list<string>  $keywords
     */
    private function matches(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (Str::contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function helpText(): string
    {
        return implode("\n", [
            'You can ask me things like:',
            '• "Give me an overview" or "Show statistics"',
            '• "List all products" / "List categories" / "List orders"',
            '• "What is the price of Laptop?"',
            '• "Show products in Electronics"',
            '• "Which products are out of stock?"',
            '• "Show the cheapest products"',
            '• "Tell me about order 1" or "product 2"',
        ]);
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function overview(): array
    {
        $products = Product::query()->count();
        $categories = Category::query()->count();
        $orders = Order::query()->count();
        $orderItems = Order_Item::query()->count();
        $users = User::query()->count();
        $inventoryValue = (float) Product::query()->selectRaw('COALESCE(SUM(price * quantity), 0) as total')->value('total');
        $avgPrice = (float) (Product::query()->avg('price') ?? 0);

        $reply = sprintf(
            "Store overview:\n• Categories: %d\n• Products: %d\n• Orders: %d\n• Order items: %d\n• Users: %d\n• Average product price: $%s\n• Inventory value (price × stock): $%s",
            $categories,
            $products,
            $orders,
            $orderItems,
            $users,
            number_format($avgPrice, 2),
            number_format($inventoryValue, 2)
        );

        return $this->response($reply, 'overview');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function productStats(string $message): array
    {
        $count = Product::query()->count();
        $totalQty = (int) Product::query()->sum('quantity');
        $avgPrice = (float) (Product::query()->avg('price') ?? 0);

        if ($this->matches($message, ['how many', 'count', 'total'])) {
            return $this->response("There are currently {$count} products in the store.", 'product_count');
        }

        return $this->response(
            "Product statistics:\n• Total products: {$count}\n• Total stock quantity: {$totalQty}\n• Average price: $".number_format($avgPrice, 2),
            'product_stats'
        );
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function categoryStats(): array
    {
        $count = Category::query()->count();

        return $this->response("There are currently {$count} categories.", 'category_count');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function orderStats(): array
    {
        $count = Order::query()->count();

        return $this->response("There are currently {$count} orders.", 'order_count');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function orderItemStats(): array
    {
        $count = Order_Item::query()->count();

        return $this->response("There are currently {$count} order items.", 'order_item_count');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function userStats(): array
    {
        $count = User::query()->count();

        return $this->response("There are currently {$count} registered users.", 'user_count');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function listProducts(): array
    {
        $products = Product::query()->with('category')->orderBy('name')->limit(15)->get();

        if ($products->isEmpty()) {
            return $this->response('No products found in the database yet.', 'products_empty');
        }

        $lines = $products->map(function (Product $product): string {
            $category = $product->category?->name ?? 'Uncategorized';

            return sprintf(
                '• #%d %s — $%s (qty: %d, category: %s)',
                $product->id,
                $product->name,
                number_format((float) $product->price, 2),
                $product->quantity,
                $category
            );
        })->implode("\n");

        $extra = Product::query()->count() > 15 ? "\n(Showing the first 15 products.)" : '';

        return $this->response("Products:\n{$lines}{$extra}", 'list_products');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function listCategories(): array
    {
        $categories = Category::query()->withCount('products')->orderBy('name')->get();

        if ($categories->isEmpty()) {
            return $this->response('No categories found in the database yet.', 'categories_empty');
        }

        $lines = $categories->map(function (Category $category): string {
            $description = filled($category->description) ? " — {$category->description}" : '';

            return sprintf(
                '• #%d %s (%d products)%s',
                $category->id,
                $category->name,
                $category->products_count,
                $description
            );
        })->implode("\n");

        return $this->response("Categories:\n{$lines}", 'list_categories');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function listOrders(): array
    {
        $orders = Order::query()
            ->with(['user', 'category', 'orderItems'])
            ->latest()
            ->limit(10)
            ->get();

        if ($orders->isEmpty()) {
            return $this->response('No orders found in the database yet.', 'orders_empty');
        }

        $lines = $orders->map(function (Order $order): string {
            $user = $order->user?->name ?? 'Unknown user';
            $category = $order->category?->name ?? 'No category';
            $items = $order->orderItems->count();
            $total = $order->orderItems->sum(fn (Order_Item $item): float => (float) $item->price * (int) $item->quantity);

            return sprintf(
                '• Order #%d — %s | category: %s | items: %d | total: $%s | created: %s',
                $order->id,
                $user,
                $category,
                $items,
                number_format((float) $total, 2),
                optional($order->created_at)?->toDateTimeString() ?? 'n/a'
            );
        })->implode("\n");

        return $this->response("Recent orders:\n{$lines}", 'list_orders');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function orderDetails(int $orderId): array
    {
        $order = Order::query()
            ->with(['user', 'category', 'orderItems.product'])
            ->find($orderId);

        if ($order === null) {
            return $this->response("I couldn't find order #{$orderId}.", 'order_not_found');
        }

        $items = $order->orderItems;
        $total = $items->sum(fn (Order_Item $item): float => (float) $item->price * (int) $item->quantity);

        $itemLines = $items->isEmpty()
            ? '• No items on this order yet.'
            : $items->map(function (Order_Item $item): string {
                $productName = $item->product?->name ?? 'Unknown product';

                return sprintf(
                    '• %s × %d @ $%s',
                    $productName,
                    $item->quantity,
                    number_format((float) $item->price, 2)
                );
            })->implode("\n");

        $reply = sprintf(
            "Order #%d details:\n• Customer: %s\n• Category: %s\n• Created: %s\n• Total: $%s\nItems:\n%s",
            $order->id,
            $order->user?->name ?? 'Unknown',
            $order->category?->name ?? 'None',
            optional($order->created_at)?->toDateTimeString() ?? 'n/a',
            number_format((float) $total, 2),
            $itemLines
        );

        return $this->response($reply, 'order_details');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function productById(int $productId): array
    {
        $product = Product::query()->with('category')->find($productId);

        if ($product === null) {
            return $this->response("I couldn't find product #{$productId}.", 'product_not_found');
        }

        return $this->response($this->formatProduct($product), 'product_by_id');
    }

    /**
     * @return array{reply: string, intent: string}|null
     */
    private function productsInCategory(string $message): ?array
    {
        $categories = Category::query()->get(['id', 'name']);

        $matched = $this->findBestNamedMatch($message, $categories->pluck('name', 'id'));

        if ($matched === null) {
            return null;
        }

        /** @var Category $category */
        $category = Category::query()->with('products')->findOrFail($matched['id']);

        if ($category->products->isEmpty()) {
            return $this->response("Category \"{$category->name}\" has no products yet.", 'category_products_empty');
        }

        $lines = $category->products->map(function (Product $product): string {
            return sprintf(
                '• #%d %s — $%s (qty: %d)',
                $product->id,
                $product->name,
                number_format((float) $product->price, 2),
                $product->quantity
            );
        })->implode("\n");

        return $this->response("Products in \"{$category->name}\":\n{$lines}", 'category_products');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function productPriceLookup(string $message): array
    {
        $search = $this->searchProducts($message);

        if ($search !== null) {
            return $search;
        }

        return $this->response(
            'I could not find a matching product. Try the exact product name, for example: "price of Laptop".',
            'price_not_found'
        );
    }

    /**
     * @return array{reply: string, intent: string}|null
     */
    private function searchProducts(string $message): ?array
    {
        $products = Product::query()->with('category')->get();

        if ($products->isEmpty()) {
            return null;
        }

        $named = $this->findBestNamedMatch(
            $message,
            $products->mapWithKeys(fn (Product $product): array => [$product->id => (string) $product->name])
        );

        if ($named === null) {
            $terms = $this->extractSearchTerms($message);

            if ($terms === []) {
                return null;
            }

            $matches = $products->filter(function (Product $product) use ($terms): bool {
                $haystack = Str::lower($product->name.' '.$product->description);

                foreach ($terms as $term) {
                    if (Str::contains($haystack, $term)) {
                        return true;
                    }
                }

                return false;
            })->take(5);

            if ($matches->isEmpty()) {
                return null;
            }

            if ($matches->count() === 1) {
                return $this->response($this->formatProduct($matches->first()), 'product_search');
            }

            $lines = $matches->map(fn (Product $product): string => $this->formatProduct($product, compact: true))->implode("\n");

            return $this->response("I found several matching products:\n{$lines}", 'product_search_multiple');
        }

        /** @var Product $product */
        $product = $products->firstWhere('id', $named['id']);

        return $this->response($this->formatProduct($product), 'product_search');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function outOfStockProducts(): array
    {
        $products = Product::query()->where('quantity', '<=', 0)->orderBy('name')->limit(15)->get();

        if ($products->isEmpty()) {
            return $this->response('All products currently have stock available.', 'out_of_stock_none');
        }

        $lines = $products->map(fn (Product $product): string => "• #{$product->id} {$product->name}")->implode("\n");

        return $this->response("Out of stock products:\n{$lines}", 'out_of_stock');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function lowStockProducts(): array
    {
        $products = Product::query()
            ->where('quantity', '>', 0)
            ->where('quantity', '<=', 5)
            ->orderBy('quantity')
            ->limit(15)
            ->get();

        if ($products->isEmpty()) {
            return $this->response('No low-stock products found (quantity 1–5).', 'low_stock_none');
        }

        $lines = $products->map(
            fn (Product $product): string => "• #{$product->id} {$product->name} — qty: {$product->quantity}"
        )->implode("\n");

        return $this->response("Low stock products:\n{$lines}", 'low_stock');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function cheapestProducts(): array
    {
        $products = Product::query()->orderBy('price')->limit(5)->get();

        if ($products->isEmpty()) {
            return $this->response('No products available.', 'cheapest_empty');
        }

        $lines = $products->map(
            fn (Product $product): string => sprintf(
                '• #%d %s — $%s',
                $product->id,
                $product->name,
                number_format((float) $product->price, 2)
            )
        )->implode("\n");

        return $this->response("Cheapest products:\n{$lines}", 'cheapest');
    }

    /**
     * @return array{reply: string, intent: string}
     */
    private function mostExpensiveProducts(): array
    {
        $products = Product::query()->orderByDesc('price')->limit(5)->get();

        if ($products->isEmpty()) {
            return $this->response('No products available.', 'expensive_empty');
        }

        $lines = $products->map(
            fn (Product $product): string => sprintf(
                '• #%d %s — $%s',
                $product->id,
                $product->name,
                number_format((float) $product->price, 2)
            )
        )->implode("\n");

        return $this->response("Most expensive products:\n{$lines}", 'most_expensive');
    }

    private function formatProduct(Product $product, bool $compact = false): string
    {
        $category = $product->category?->name ?? 'Uncategorized';

        if ($compact) {
            return sprintf(
                '• #%d %s — $%s (qty: %d, %s)',
                $product->id,
                $product->name,
                number_format((float) $product->price, 2),
                $product->quantity,
                $category
            );
        }

        $description = filled($product->description) ? $product->description : 'No description provided.';

        return sprintf(
            "Product #%d — %s\n• Price: $%s\n• Quantity: %d\n• Category: %s\n• Description: %s",
            $product->id,
            $product->name,
            number_format((float) $product->price, 2),
            $product->quantity,
            $category,
            $description
        );
    }

    /**
     * @param  Collection<int|string, string>  $namesById
     * @return array{id: int|string, name: string}|null
     */
    private function findBestNamedMatch(string $message, Collection $namesById): ?array
    {
        $best = null;
        $bestLength = 0;

        foreach ($namesById as $id => $name) {
            $needle = Str::lower(trim($name));

            if ($needle === '' || ! Str::contains($message, $needle)) {
                continue;
            }

            $length = Str::length($needle);

            if ($length > $bestLength) {
                $bestLength = $length;
                $best = ['id' => $id, 'name' => $name];
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private function extractSearchTerms(string $message): array
    {
        $cleaned = Str::of($message)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->toString();

        $stopWords = [
            'a', 'an', 'the', 'of', 'for', 'to', 'in', 'on', 'at', 'is', 'are', 'was', 'were',
            'what', 'which', 'who', 'how', 'many', 'much', 'me', 'my', 'about', 'tell', 'show',
            'list', 'find', 'search', 'look', 'please', 'price', 'cost', 'product', 'products',
            'category', 'categories', 'order', 'orders', 'item', 'items', 'give', 'get', 'info',
            'details', 'do', 'you', 'have', 'any', 'all', 'with', 'from', 'and', 'or',
        ];

        return array_values(array_filter(
            preg_split('/\s+/', $cleaned) ?: [],
            fn (string $term): bool => strlen($term) >= 3 && ! in_array($term, $stopWords, true)
        ));
    }
}
