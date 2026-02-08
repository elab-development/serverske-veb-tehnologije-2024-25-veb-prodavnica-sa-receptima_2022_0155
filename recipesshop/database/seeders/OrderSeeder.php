<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\User;
use App\Models\OrderItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $ingredients = Ingredient::all();
        $orders = [];
        for ($i = 0; $i < 5; $i++) {
            $orders[] = Order::factory()->create([
                'user_id' => $users->random()->user_id,
            ]);
        }

        foreach ($orders as $order) {
            $itemCount = rand(1, 5);
            $totalPrice = 0;
            for ($j = 0; $j < $itemCount; $j++) {
                $ingredient = $ingredients->random();
                $amount = rand(1, 3);
                $price = $ingredient->price * $amount;
                OrderItem::factory()->create([
                    'order_id'     => $order->order_id,
                    'user_id'      => $order->user_id,
                    'ingredient_id'=> $ingredient->ingredient_id,
                    'amount'       => $amount,
                    'total_price'  => $price,
                ]);
                $totalPrice += $price;
            }
            $order->total_price = $totalPrice;
            $order->save();
        }
    }
}
