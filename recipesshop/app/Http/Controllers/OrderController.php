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
     * @OA\Get(
     *   path="/api/orders",
     *   tags={"Orders"},
     *   summary="List orders (admin: all, user: own only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="orders", type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="id", type="integer", example=12),
     *           @OA\Property(property="status", type="string", example="pending"),
     *           @OA\Property(property="total_amount", type="number", format="float", example=12.30),
     *           @OA\Property(property="ingredient_ids", type="array", @OA\Items(type="integer"), example={1,2,5}),
     *           @OA\Property(property="user", type="object",
     *             @OA\Property(property="id", type="integer", example=3),
     *             @OA\Property(property="name", type="string", example="Jane Doe"),
     *             @OA\Property(property="email", type="string", example="jane@example.com")
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="No orders found.")
     * )
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
     * @OA\Get(
     *   path="/api/users/{user}/orders",
     *   tags={"Orders"},
     *   summary="Admin: list orders for a specific user",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="user", in="path", required=true, description="User ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="user", type="object",
     *         @OA\Property(property="id", type="integer", example=3),
     *         @OA\Property(property="name", type="string", example="Jane Doe"),
     *         @OA\Property(property="email", type="string", example="jane@example.com")
     *       ),
     *       @OA\Property(property="orders", type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="id", type="integer", example=15),
     *           @OA\Property(property="status", type="string", example="paid"),
     *           @OA\Property(property="total_amount", type="number", format="float", example=8.70),
     *           @OA\Property(property="ingredient_ids", type="array", @OA\Items(type="integer"), example={10,14})
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can view user orders"),
     *   @OA\Response(response=404, description="No orders found for this user.")
     * )
     */

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
     * @OA\Post(
     *   path="/api/orders",
     *   tags={"Orders"},
     *   summary="Create an order from ingredient IDs (user only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"ingredient_ids"},
     *       @OA\Property(property="ingredient_ids", type="array", @OA\Items(type="integer"), example={1,2,5})
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Order created",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Order created successfully"),
     *       @OA\Property(property="order", type="object",
     *         @OA\Property(property="id", type="integer", example=22),
     *         @OA\Property(property="status", type="string", example="pending"),
     *         @OA\Property(property="total_amount", type="number", format="float", example=12.30),
     *         @OA\Property(property="ingredient_ids", type="array", @OA\Items(type="integer"), example={1,2,5})
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only users can create orders"),
     *   @OA\Response(response=422, description="Validation error")
     * )
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
     * @OA\Post(
     *   path="/api/orders/from-recipes",
     *   tags={"Orders"},
     *   summary="Create an order from recipes, with include/exclude ingredients (user only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"recipe_ids"},
     *       @OA\Property(property="recipe_ids", type="array", @OA\Items(type="integer"), example={1,2}),
     *       @OA\Property(property="include_ingredient_ids", type="array", @OA\Items(type="integer"), example={19}),
     *       @OA\Property(property="exclude_ingredient_ids", type="array", @OA\Items(type="integer"), example={3})
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Order created from recipes",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Order created successfully from recipes"),
     *       @OA\Property(property="order", type="object",
     *         @OA\Property(property="id", type="integer", example=23),
     *         @OA\Property(property="status", type="string", example="pending"),
     *         @OA\Property(property="total_amount", type="number", format="float", example=18.40),
     *         @OA\Property(property="ingredient_ids", type="array", @OA\Items(type="integer"), example={1,2,5,19})
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only users can create orders"),
     *   @OA\Response(response=422, description="Selected recipes and modifiers resulted in an empty cart.")
     * )
     */

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
     * @OA\Get(
     *   path="/api/orders/{order}",
     *   tags={"Orders"},
     *   summary="Get a single order (admin:any, user:own only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="order", in="path", required=true, description="Order ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="order", type="object",
     *         @OA\Property(property="id", type="integer", example=22),
     *         @OA\Property(property="status", type="string", example="pending"),
     *         @OA\Property(property="total_amount", type="number", format="float", example=12.30),
     *         @OA\Property(property="ingredient_ids", type="array", @OA\Items(type="integer"), example={1,2,5})
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Forbidden")
     * )
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
     * @OA\Put(
     *   path="/api/orders/{order}",
     *   tags={"Orders"},
     *   summary="Update an order (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="order", in="path", required=true, description="Order ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="string", enum={"pending","paid","fulfilled","cancelled"}, example="paid")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Order updated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Order updated successfully"),
     *       @OA\Property(property="order", type="object",
     *         @OA\Property(property="id", type="integer", example=22),
     *         @OA\Property(property="status", type="string", example="paid"),
     *         @OA\Property(property="total_amount", type="number", format="float", example=12.30),
     *         @OA\Property(property="ingredient_ids", type="array", @OA\Items(type="integer"), example={1,2,5})
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can update orders"),
     *   @OA\Response(response=422, description="Validation error")
     * )
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
}