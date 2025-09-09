<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Ingredient;
use App\Models\Order;
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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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