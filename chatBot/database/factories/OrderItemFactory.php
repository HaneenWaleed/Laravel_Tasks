<?php

namespace Database\Factories;

use App\Models\Model;
use App\Models\Order;
use App\Models\Order_Item;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Model>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Order_Item::class;
    public function definition(): array
    {
        return [
            //
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'price' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
