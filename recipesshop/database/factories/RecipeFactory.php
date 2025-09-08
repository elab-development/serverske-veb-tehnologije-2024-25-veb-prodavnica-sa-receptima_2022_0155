<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(3, true)),
            'description' => $this->faker->optional()->paragraph(),
            'ingredient_ids' => []
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Recipe $recipe) {
            if (empty($recipe->ingredient_ids)) {
                $ids = Ingredient::query()->inRandomOrder()->limit(rand(0, 5))->pluck('id')->all();
                $recipe->ingredient_ids = array_values(array_unique($ids));
            }
        })->afterCreating(function (Recipe $recipe) {
            if ($recipe->wasChanged('ingredient_ids') === false && empty($recipe->ingredient_ids)) {
                $ids = Ingredient::query()->inRandomOrder()->limit(rand(0, 5))->pluck('id')->all();
                $recipe->update(['ingredient_ids' => array_values(array_unique($ids))]);
            }
        });
    }

    public function withIngredients(array $ingredientIds): static
    {
        return $this->state(fn() => ['ingredient_ids' => array_values(array_unique($ingredientIds))]);
    }

    public function withIngredientCount(int $count): static
    {
        return $this->state(function () use ($count) {
            $ids = Ingredient::query()->inRandomOrder()->limit(max(0, $count))->pluck('id')->all();
            return ['ingredient_ids' => array_values(array_unique($ids))];
        });
    }
}
