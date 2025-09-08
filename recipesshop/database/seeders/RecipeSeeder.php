<?php

namespace Database\Seeders;

use App\Models\Recipe;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $recipes = [
            [
                'name' => 'Greek Salad',
                'description' => 'Fresh salad with tomato, cucumber, onion, and olive oil.',
                'ingredient_ids' => [1, 2, 3, 5, 18],
            ],
            [
                'name' => 'Spaghetti Bolognese',
                'description' => 'Classic Italian pasta with beef and tomato sauce.',
                'ingredient_ids' => [11, 1, 3, 5, 15, 16],
            ],
            [
                'name' => 'Chicken Curry',
                'description' => 'Spicy chicken curry with rice.',
                'ingredient_ids' => [10, 13, 14, 3, 4],
            ],
            [
                'name' => 'Omelette',
                'description' => 'Egg omelette with cheese and parsley.',
                'ingredient_ids' => [8, 6, 17],
            ],
            [
                'name' => 'Mashed Potatoes',
                'description' => 'Creamy mashed potatoes with butter and milk.',
                'ingredient_ids' => [12, 9, 19],
            ],
        ];

        foreach ($recipes as $recipe) {
            Recipe::create($recipe);
        }
    }
}
