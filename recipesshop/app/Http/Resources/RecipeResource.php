<?php

namespace App\Http\Resources;

use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->recipe_id,         
            'recipe_id' => $this->recipe_id, 
            'name' => $this->name,
            'description' => $this->description,
            'ingredients_count' => $this->when(isset($this->ingredients_count), (int) $this->ingredients_count),
            'ingredients' => $this->whenLoaded('ingredients', function () {
                return $this->ingredients->map(function ($ing) {
                    return [
                        'id' => $ing->ingredient_id,
                        'ingredient_id' => $ing->ingredient_id,
                        'name' => $ing->name,
                        'unit' => $ing->unit,
                        'price' => (float) $ing->price,
                        'quantity' => (int) ($ing->pivot->quantity ?? 1),
                    ];
                })->values();
            }, []),
        ];
    }
}
