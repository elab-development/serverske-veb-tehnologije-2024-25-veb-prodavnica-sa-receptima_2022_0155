<?php

namespace App\Http\Resources;

use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ingredients = Ingredient::whereIn('id', $this->ingredient_ids ?? [])->get();

        return [
            'id' => $this->id,
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'ingredients' => IngredientResource::collection($ingredients),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
