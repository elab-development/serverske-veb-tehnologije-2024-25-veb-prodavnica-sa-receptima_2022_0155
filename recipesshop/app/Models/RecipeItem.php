<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'recipe_item_id';
    protected $fillable = ['recipe_id', 'ingredient_id', 'quantity'];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'recipe_id', 'recipe_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }
    
    public function getRouteKeyName()
    {
        return $this->primaryKey;
    }

}
