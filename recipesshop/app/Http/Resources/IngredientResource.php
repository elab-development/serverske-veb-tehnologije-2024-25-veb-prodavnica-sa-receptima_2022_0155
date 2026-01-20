<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $id = $this->id ?? $this->ingredient_id;
        return [
            'id'          => $id,
            'name'        => $this->name,
            'unit'        => $this->unit,
            'price'       => $this->price,
            'photo_url'   => $this->photo_path,
            'description' => $this->description,
            'category'    => $this->category,
            'type'        => $this->type,
            ];
        }
}
