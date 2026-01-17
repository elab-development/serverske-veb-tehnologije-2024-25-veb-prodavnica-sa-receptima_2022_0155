<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Models\Ingredient;

class CartSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();
        $ingredients = Ingredient::all();
        $carts = [];
        foreach ($users as $user) {
            $carts[] = Cart::factory()->create([
                'user_id' => $user->user_id,
            ]);
        }

        foreach ($carts as $cart) {
            $totalItems = 0;
            $totalPrice = 0;
            $itemCount = rand(0, 5);
            for ($k = 0; $k < $itemCount; $k++) {
                $ingredient = $ingredients->random();
                $amount = rand(1, 3);
                CartItem::factory()->create([
                    'cart_id'       => $cart->cart_id,
                    'ingredient_id' => $ingredient->ingredient_id,
                    'amount'        => $amount,
                ]);
                $totalItems += $amount;
                $totalPrice += $ingredient->price * $amount;
            }
            $cart->total_amount_of_items = $totalItems;
            $cart->total_price = $totalPrice;
            $cart->save();
        }
    }
}
