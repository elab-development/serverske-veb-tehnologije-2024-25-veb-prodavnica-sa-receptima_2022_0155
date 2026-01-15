<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;
    protected $table = 'ingredients';
    protected $primaryKey = 'ingredient_id';

    protected $fillable = [
        'name',
        'price',
        'unit',
        'photo_path',
        'description',
        'category',
        'type',
    ];
    
    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_items', 'ingredient_id', 'recipe_id')
                    ->withPivot('quantity');
    }
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items', 'ingredient_id', 'order_id')
                    ->withPivot('amount', 'total_price', 'user_id');
    }
    public function getRouteKeyName()
    {
        return $this->primaryKey;
    }
}
