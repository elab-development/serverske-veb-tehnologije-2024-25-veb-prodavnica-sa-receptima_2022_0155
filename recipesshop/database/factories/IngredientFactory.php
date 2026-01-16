<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Ingredient::class;

    public function definition()
    {
        $map = [
            'voce' => [
                'orasasto',   
                'citrus',
                'bobicaste',
                'kosticavo',
                'tropsko',
            ],
            'povrce' => [
                'lisnato',
                'korenasto',
                'lukovicasto',
                'plodovito',
                'krtole',
            ],
        ];

        $category = $this->faker->randomElement(array_keys($map));
        $type = $this->faker->randomElement($map[$category]);

        $unit = $this->faker->randomElement(['kg', 'kom']);

        if ($category === 'voce') {
            $min = 80;  $max = 450;
            if ($type === 'orasasto') { $min = 300; $max = 1200; }
        } else { 
            $min = 50;  $max = 350;
        }

        return [
            'name' => $this->faker->unique()->words(2, true),   
            'price' => $this->faker->randomFloat(2, $min, $max),
            'unit' => $unit,

            'description' => $this->faker->optional(0.7)->sentence(10),
            'category' => $category,
            'type' => $type,

            'photo_path' => null,
        ];
    }
}
