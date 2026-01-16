<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\User;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = OrderItem::class;

    public function definition()
    {
        return [
           'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'ingredient_id' => Ingredient::factory(),
            'amount' => $this->faker->numberBetween(1, 5),
            'total_price' => $this->faker->randomFloat(2, 1, 200),
        ];
    }
}
