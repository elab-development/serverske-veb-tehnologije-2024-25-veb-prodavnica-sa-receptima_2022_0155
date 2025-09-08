<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ingredients = [
            ['name' => 'Tomato', 'price' => 1.20],
            ['name' => 'Cucumber', 'price' => 0.80],
            ['name' => 'Onion', 'price' => 0.60],
            ['name' => 'Garlic', 'price' => 0.40],
            ['name' => 'Olive Oil', 'price' => 5.50],
            ['name' => 'Cheese', 'price' => 3.20],
            ['name' => 'Flour', 'price' => 1.00],
            ['name' => 'Eggs', 'price' => 2.50],
            ['name' => 'Milk', 'price' => 1.10],
            ['name' => 'Chicken Breast', 'price' => 6.90],
            ['name' => 'Beef Steak', 'price' => 12.50],
            ['name' => 'Potato', 'price' => 0.90],
            ['name' => 'Carrot', 'price' => 0.70],
            ['name' => 'Rice', 'price' => 2.30],
            ['name' => 'Spaghetti', 'price' => 2.00],
            ['name' => 'Basil', 'price' => 0.50],
            ['name' => 'Parsley', 'price' => 0.40],
            ['name' => 'Lettuce', 'price' => 1.30],
            ['name' => 'Butter', 'price' => 2.10],
            ['name' => 'Yogurt', 'price' => 1.70],
        ];

        foreach ($ingredients as $ingredient) {
            Ingredient::create($ingredient);
        }
    }
}
