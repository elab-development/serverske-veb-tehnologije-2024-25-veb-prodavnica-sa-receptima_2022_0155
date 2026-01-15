<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_items', 'recipe_id', 'ingredient_id')
                    ->withPivot('quantity');
    }

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class, 'recipe_id', 'recipe_id');
    }
    
    public function getRouteKeyName()
    {
        return $this->primaryKey;
    }
}
