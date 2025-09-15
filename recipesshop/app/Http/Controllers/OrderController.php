<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::user()->role === 'admin') {
            $orders = Order::with('user')->latest()->get();
        } else {
            $orders = Order::with('user')->where('user_id', Auth::id())->latest()->get();
        }

        if ($orders->isEmpty()) {
            return response()->json('No orders found.', 404);
        }

        return response()->json([
            'orders' => OrderResource::collection($orders),
        ]);
    }

    public function forUser(User $user)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can view user orders'], 403);
        }

        $orders = Order::with('user')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        if ($orders->isEmpty()) {
            return response()->json('No orders found for this user.', 404);
        }

        return response()->json([
            'user'   => new UserResource($user),
            'orders' => OrderResource::collection($orders),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (Auth::user()->role !== 'user') {
            return response()->json(['error' => 'Only users can create orders'], 403);
        }

        $validated = $request->validate([
            'ingredient_ids' => ['required', 'array', 'min:1'],
            'ingredient_ids.*' => ['integer', 'distinct', 'exists:ingredients,id'],
        ]);

        $ids = array_values(array_unique($validated['ingredient_ids']));
        $total = (float) Ingredient::whereIn('id', $ids)->sum('price');

        $order = Order::create([
            'user_id' => Auth::id(),
            'ingredient_ids' => $ids,
            'total_amount' => number_format($total, 2, '.', ''),
            'status' => 'pending',
        ]);

        $order->load('user');

        return response()->json([
            'message' => 'Order created successfully',
            'order'   => new OrderResource($order),
        ], 201);
    }

    public function storeFromRecipes(Request $request)
    {
        if (Auth::user()->role !== 'user') {
            return response()->json(['error' => 'Only users can create orders'], 403);
        }

        $validated = $request->validate([
            'recipe_ids' => ['required', 'array', 'min:1'],
            'recipe_ids.*' => ['integer', 'distinct', 'exists:recipes,id'],
            'include_ingredient_ids' => ['sometimes', 'array'],
            'include_ingredient_ids.*' => ['integer', 'distinct', 'exists:ingredients,id'],
            'exclude_ingredient_ids' => ['sometimes', 'array'],
            'exclude_ingredient_ids.*' => ['integer', 'distinct', 'exists:ingredients,id'],
        ]);

        $recipes = Recipe::whereIn('id', $validated['recipe_ids'])->get(['ingredient_ids']);
        $baseIds = [];
        foreach ($recipes as $r) {
            if (is_array($r->ingredient_ids)) {
                $baseIds = array_merge($baseIds, $r->ingredient_ids);
            }
        }

        $include = $validated['include_ingredient_ids'] ?? [];
        $exclude = $validated['exclude_ingredient_ids'] ?? [];

        $baseIds = array_values(array_unique(array_map('intval', $baseIds)));
        $include = array_values(array_unique(array_map('intval', $include)));
        $exclude = array_values(array_unique(array_map('intval', $exclude)));

        $finalIds = array_values(array_unique(array_merge($baseIds, $include)));
        if (!empty($exclude)) {
            $finalIds = array_values(array_diff($finalIds, $exclude));
        }

        if (empty($finalIds)) {
            return response()->json([
                'error' => 'Selected recipes and modifiers resulted in an empty cart.'
            ], 422);
        }

        $total = (float) Ingredient::whereIn('id', $finalIds)->sum('price');

        $order = Order::create([
            'user_id' => Auth::id(),
            'ingredient_ids' => $finalIds,
            'total_amount' => number_format($total, 2, '.', ''),
            'status' => 'pending',
        ]);

        $order->load('user');

        return response()->json([
            'message' => 'Order created successfully from recipes',
            'order' => new OrderResource($order),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        if (Auth::user()->role !== 'admin' && $order->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $order->load('user');

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can update orders'], 403);
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,paid,fulfilled,cancelled'],
        ]);

        $order->update($validated);
        $order->load('user');

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}