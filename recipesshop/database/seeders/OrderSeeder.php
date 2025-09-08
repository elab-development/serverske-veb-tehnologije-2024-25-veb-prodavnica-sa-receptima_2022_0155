<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role', 'user')->get();

        foreach ($users as $user) {
            $order1Ingredients = [1, 2, 5];
            $order2Ingredients = [10, 14];

            $total1 = Ingredient::whereIn('id', $order1Ingredients)->sum('price');
            $total2 = Ingredient::whereIn('id', $order2Ingredients)->sum('price');

            Order::create([
                'user_id' => $user->id,
                'ingredient_ids' => $order1Ingredients,
                'total_amount' => $total1,
                'status' => 'pending',
            ]);

            Order::create([
                'user_id' => $user->id,
                'ingredient_ids' => $order2Ingredients,
                'total_amount' => $total2,
                'status' => 'paid',
            ]);
        }
    }
}
