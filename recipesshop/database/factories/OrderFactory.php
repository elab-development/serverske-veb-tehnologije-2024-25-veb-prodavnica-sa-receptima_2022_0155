<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ingredient_ids' => [],
            'total_amount' => 0,
            'status' => $this->faker->randomElement(['pending', 'paid', 'fulfilled', 'cancelled']),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Order $order) {
            if (empty($order->ingredient_ids)) {
                $ids = Ingredient::query()->inRandomOrder()->limit(rand(1, 5))->pluck('id')->all();
                $order->ingredient_ids = array_values(array_unique($ids));
            }
            $order->total_amount = $this->calculateTotal($order->ingredient_ids);
        })->afterCreating(function (Order $order) {
            $freshTotal = $this->calculateTotal($order->ingredient_ids);
            if ((float)$order->total_amount !== (float)$freshTotal) {
                $order->update(['total_amount' => $freshTotal]);
            }
        });
    }

    public function withItems(array $ingredientIds): static
    {
        $ids = array_values(array_unique(array_map('intval', $ingredientIds)));
        return $this->state(fn() => [
            'ingredient_ids' => $ids,
            'total_amount' => $this->calculateTotal($ids),
        ]);
    }

    private function calculateTotal(array $ids): float
    {
        if (empty($ids)) return 0.0;
        $sum = Ingredient::query()->whereIn('id', $ids)->sum('price');
        return (float) number_format($sum, 2, '.', '');
    }
}
