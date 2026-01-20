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
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

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
     *       @OA\Property(
     *         property="orders",
     *         type="array",
     *         description="List of orders",
     *         @OA\Items(
     *           type="object",
     *
     *           @OA\Property(property="order_id", type="integer", example=12),
     *           @OA\Property(property="status", type="string", example="pending"),
     *           @OA\Property(property="total_price", type="number", format="float", example=65.0),
     *
     *           @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example="2026-01-19T10:35:12Z"),
     *           @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example="2026-01-19T10:36:40Z"),
     *
     *           @OA\Property(
     *             property="user",
     *             type="object",
     *             nullable=true,
     *             @OA\Property(property="user_id", type="integer", example=3),
     *             @OA\Property(property="email", type="string", format="email", example="jane@example.com"),
     *             @OA\Property(property="role", type="string", example="user")
     *           ),
     *
     *           @OA\Property(
     *             property="items",
     *             type="array",
     *             @OA\Items(
     *               type="object",
     *               @OA\Property(property="order_item_id", type="integer", example=25),
     *
     *               @OA\Property(
     *                 property="ingredient",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="ingredient_id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Beli luk"),
     *                 @OA\Property(property="unit", type="string", example="kom"),
     *                 @OA\Property(property="price", type="number", format="float", example=30.0)
     *               ),
     *
     *               @OA\Property(property="amount", type="integer", example=1),
     *               @OA\Property(property="total_price", type="number", format="float", example=30.0)
     *             )
     *           )
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function index()
    {
        $q = Order::query()->with(['user', 'orderItems.ingredient']);

        if (Auth::user()->role !== 'admin') {
            $q->where('user_id', Auth::id());
        }

        $orders = $q->latest()->get();
        return response()->json([
            'orders' => $orders->map(fn($o) => $this->presentOrder($o))->values(),
        ]);
    }
    private function presentOrder(Order $order): array
    {
        return [
            'order_id' => $order->order_id,
            'status' => $order->status,
            'total_price' => (float)$order->total_price,

            'created_at' => $order->created_at ? $order->created_at->toISOString() : null,
            'updated_at' => $order->updated_at ? $order->updated_at->toISOString() : null,

            'user' => $order->relationLoaded('user') && $order->user ? [
                'user_id' => $order->user->user_id,
                'email' => $order->user->email,
                'role' => $order->user->role,
            ] : null,

            'items' => $order->orderItems->map(function ($it) {
                return [
                    'order_item_id' => $it->order_item_id,
                    'ingredient' => $it->ingredient ? [
                        'ingredient_id' => $it->ingredient->ingredient_id,
                        'name' => $it->ingredient->name,
                        'unit' => $it->ingredient->unit,
                        'price' => (float)$it->ingredient->price,
                    ] : null,
                    'amount' => (int)$it->amount,
                    'total_price' => (float)$it->total_price,
                ];
            })->values(),
        ];
    }

    /**
     * @OA\Get(
     *   path="/api/users/{user}/orders",
     *   tags={"Orders"},
     *   summary="Admin: list orders for a specific user",
     *   description="Returns orders for the specified user",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="user",
     *     in="path",
     *     required=true,
     *     description="User ID (route-model binding)",
     *     @OA\Schema(type="integer", example=3)
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"user","orders"},
     *       @OA\Property(
     *         property="user",
     *         type="object",
     *         required={"user_id","email","role"},
     *         @OA\Property(property="user_id", type="integer", example=3),
     *         @OA\Property(property="email", type="string", format="email", example="jane@example.com"),
     *         @OA\Property(property="role", type="string", example="user")
     *       ),
     *       @OA\Property(
     *         property="orders",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           required={"order_id","status","total_price","created_at","updated_at","user","items"},
     *
     *           @OA\Property(property="order_id", type="integer", example=15),
     *           @OA\Property(property="status", type="string", example="paid"),
     *           @OA\Property(property="total_price", type="number", format="float", example=65.00),
     *
     *           @OA\Property(
     *             property="created_at",
     *             type="string",
     *             format="date-time",
     *             nullable=true,
     *             example="2026-01-19T21:52:03.379Z"
     *           ),
     *           @OA\Property(
     *             property="updated_at",
     *             type="string",
     *             format="date-time",
     *             nullable=true,
     *             example="2026-01-19T21:55:10.120Z"
     *           ),
     *
     *           @OA\Property(
     *             property="user",
     *             type="object",
     *             nullable=true,
     *             required={"user_id","email","role"},
     *             @OA\Property(property="user_id", type="integer", example=3),
     *             @OA\Property(property="email", type="string", format="email", example="jane@example.com"),
     *             @OA\Property(property="role", type="string", example="user")
     *           ),
     *
     *           @OA\Property(
     *             property="items",
     *             type="array",
     *             @OA\Items(
     *               type="object",
     *               required={"order_item_id","ingredient","amount","total_price"},
     *               @OA\Property(property="order_item_id", type="integer", example=25),
     *
     *               @OA\Property(
     *                 property="ingredient",
     *                 type="object",
     *                 nullable=true,
     *                 required={"ingredient_id","name","unit","price"},
     *                 @OA\Property(property="ingredient_id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Beli luk"),
     *                 @OA\Property(property="unit", type="string", example="kom"),
     *                 @OA\Property(property="price", type="number", format="float", example=30.00)
     *               ),
     *
     *               @OA\Property(property="amount", type="integer", example=1),
     *               @OA\Property(property="total_price", type="number", format="float", example=30.00)
     *             )
     *           )
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Only admins can view user orders",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="error", type="string", example="Only admins can view user orders")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="No orders found for this user."
     *   )
     * )
     */

    public function forUser(User $user)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can view user orders'], 403);
        }

        $orders = Order::query()
            ->with(['user', 'orderItems.ingredient'])
            ->where('user_id', $user->user_id)
            ->latest()
            ->get();

         return response()->json([
            'user' => [
                'user_id' => $user->user_id,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'orders' => $orders->map(fn($o) => $this->presentOrder($o))->values(),
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/orders",
     *   tags={"Orders"},
     *   summary="Create an order (user only)",
     *   description="Creates a new order for the authenticated user",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(
     *           required={"items"},
     *           @OA\Property(
     *             property="items",
     *             type="array",
     *             minItems=1,
     *             @OA\Items(
     *               type="object",
     *               required={"ingredient_id","amount"},
     *               @OA\Property(property="ingredient_id", type="integer", example=2),
     *               @OA\Property(property="amount", type="integer", minimum=1, example=3)
     *             )
     *           )
     *         ),
     *         @OA\Schema(
     *           required={"ingredient_ids"},
     *           @OA\Property(
     *             property="ingredient_ids",
     *             type="array",
     *             minItems=1,
     *             @OA\Items(type="integer"),
     *             example={2,26}
     *           )
     *         )
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Order created",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"message","order"},
     *       @OA\Property(property="message", type="string", example="Order created successfully"),
     *       @OA\Property(
     *         property="order",
     *         type="object",
     *         required={"order_id","status","total_price","created_at","updated_at","user","items"},
     *
     *         @OA\Property(property="order_id", type="integer", example=22),
     *         @OA\Property(property="status", type="string", example="pending"),
     *         @OA\Property(property="total_price", type="number", format="float", example=65.00),
     *
     *         @OA\Property(
     *           property="created_at",
     *           type="string",
     *           format="date-time",
     *           nullable=true,
     *           example="2026-01-19T21:52:03.379Z"
     *         ),
     *         @OA\Property(
     *           property="updated_at",
     *           type="string",
     *           format="date-time",
     *           nullable=true,
     *           example="2026-01-19T21:55:10.120Z"
     *         ),
     *
     *         @OA\Property(
     *           property="user",
     *           type="object",
     *           nullable=true,
     *           required={"user_id","email","role"},
     *           @OA\Property(property="user_id", type="integer", example=3),
     *           @OA\Property(property="email", type="string", format="email", example="jane@example.com"),
     *           @OA\Property(property="role", type="string", example="user")
     *         ),
     *
     *         @OA\Property(
     *           property="items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             required={"order_item_id","ingredient","amount","total_price"},
     *             @OA\Property(property="order_item_id", type="integer", example=25),
     *             @OA\Property(
     *               property="ingredient",
     *               type="object",
     *               nullable=true,
     *               required={"ingredient_id","name","unit","price"},
     *               @OA\Property(property="ingredient_id", type="integer", example=2),
     *               @OA\Property(property="name", type="string", example="Beli luk"),
     *               @OA\Property(property="unit", type="string", example="kom"),
     *               @OA\Property(property="price", type="number", format="float", example=30.00)
     *             ),
     *             @OA\Property(property="amount", type="integer", example=1),
     *             @OA\Property(property="total_price", type="number", format="float", example=30.00)
     *           )
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated"
     *   ),
     *
     *   @OA\Response(
     *     response=403,
     *     description="Only users can create orders",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="error", type="string", example="Only users can create orders")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=422,
     *     description="Validation error / Empty cart",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="error", type="string", example="Empty cart")
     *     )
     *   )
     * )
     */

    public function store(Request $request)
    {
        if (Auth::user()->role !== 'user') {
            return response()->json(['error' => 'Only users can create orders'], 403);
        }

        $request->validate([
            'items' => 'sometimes|array|min:1',
            'items.*.ingredient_id' => 'required_with:items|integer|distinct|exists:ingredients,ingredient_id',
            'items.*.amount' => 'required_with:items|integer|min:1',
            'ingredient_ids' => 'sometimes|array|min:1',
            'ingredient_ids.*' => 'integer|distinct|exists:ingredients,ingredient_id',
        ]);

        $items = $this->normalizeOrderItems($request);

        if (empty($items)) {
            return response()->json(['error' => 'Empty cart'], 422);
        }

        return DB::transaction(function () use ($items) {
            $order = Order::create([
                'user_id' => Auth::id(),
                'status' => 'pending',
                'total_price' => 0,
            ]);

            $total = 0.0;

            $ingredients = Ingredient::query()
                ->whereIn('ingredient_id', array_column($items, 'ingredient_id'))
                ->get()
                ->keyBy('ingredient_id');

            foreach ($items as $row) {
                $ing = $ingredients[$row['ingredient_id']];
                $line = (float)$ing->price * (int)$row['amount'];
                $total += $line;

                OrderItem::create([
                    'order_id' => $order->order_id,
                    'user_id' => Auth::id(),
                    'ingredient_id' => $ing->ingredient_id,
                    'amount' => (int)$row['amount'],
                    'total_price' => number_format($line, 2, '.', ''),
                ]);
            }

            $order->update(['total_price' => number_format($total, 2, '.', '')]);

            $order->load(['user', 'orderItems.ingredient']);

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $this->presentOrder($order),
            ], 201);
        });
    }
    private function normalizeOrderItems(Request $request): array
    {
        if ($request->has('items')) {
            return collect($request->input('items', []))
                ->map(fn($x) => [
                    'ingredient_id' => (int)$x['ingredient_id'],
                    'amount' => (int)$x['amount'],
                ])
                ->values()
                ->all();
        }

        $ids = $request->input('ingredient_ids', []);
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return array_map(fn($id) => ['ingredient_id' => $id, 'amount' => 1], $ids);
    }

    /**
     * @OA\Get(
     *   path="/api/orders/{order}",
     *   tags={"Orders"},
     *   summary="Get a single order (admin:any, user:own only)",
     *   description="Admins can view any order. Regular users can view only their own order.",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="order",
     *     in="path",
     *     required=true,
     *     description="Order ID (route-model binding)",
     *     @OA\Schema(type="integer", example=22)
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"order"},
     *       @OA\Property(
     *         property="order",
     *         type="object",
     *         required={"order_id","status","total_price","created_at","updated_at","user","items"},
     *
     *         @OA\Property(property="order_id", type="integer", example=22),
     *         @OA\Property(property="status", type="string", example="pending"),
     *         @OA\Property(property="total_price", type="number", format="float", example=12.30),
     *
     *         @OA\Property(
     *           property="created_at",
     *           type="string",
     *           format="date-time",
     *           nullable=true,
     *           example="2026-01-19T21:52:03.379Z"
     *         ),
     *         @OA\Property(
     *           property="updated_at",
     *           type="string",
     *           format="date-time",
     *           nullable=true,
     *           example="2026-01-19T21:55:10.120Z"
     *         ),
     *
     *         @OA\Property(
     *           property="user",
     *           type="object",
     *           nullable=true,
     *           required={"user_id","email","role"},
     *           @OA\Property(property="user_id", type="integer", example=3),
     *           @OA\Property(property="email", type="string", format="email", example="jane@example.com"),
     *           @OA\Property(property="role", type="string", example="user")
     *         ),
     *
     *         @OA\Property(
     *           property="items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             required={"order_item_id","ingredient","amount","total_price"},
     *             @OA\Property(property="order_item_id", type="integer", example=25),
     *
     *             @OA\Property(
     *               property="ingredient",
     *               type="object",
     *               nullable=true,
     *               required={"ingredient_id","name","unit","price"},
     *               @OA\Property(property="ingredient_id", type="integer", example=2),
     *               @OA\Property(property="name", type="string", example="Beli luk"),
     *               @OA\Property(property="unit", type="string", example="kom"),
     *               @OA\Property(property="price", type="number", format="float", example=30.00)
     *             ),
     *
     *             @OA\Property(property="amount", type="integer", example=1),
     *             @OA\Property(property="total_price", type="number", format="float", example=30.00)
     *           )
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="error", type="string", example="Forbidden")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found"
     *   )
     * )
     */

    public function show(Order $order)
    {
        if (Auth::user()->role !== 'admin' && (int)$order->user_id !== (int)Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $order->load(['user', 'orderItems.ingredient']);

        return response()->json([
            'order' => $this->presentOrder($order),
        ]);
    }

    /**
     * @OA\Put(
     *   path="/api/orders/{order}",
     *   tags={"Orders"},
     *   summary="Update an order (admin only)",
     *   description="Updates an order status",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="order",
     *     in="path",
     *     required=true,
     *     description="Order ID",
     *     @OA\Schema(type="integer", example=22)
     *   ),
     *
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="status",
     *         type="string",
     *         enum={"pending","paid","fulfilled","cancelled"},
     *         example="paid"
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Order updated",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"message","order"},
     *       @OA\Property(property="message", type="string", example="Order updated successfully"),
     *       @OA\Property(
     *         property="order",
     *         type="object",
     *         required={"order_id","status","total_price","created_at","updated_at","user","items"},
     *
     *         @OA\Property(property="order_id", type="integer", example=22),
     *         @OA\Property(property="status", type="string", example="paid"),
     *         @OA\Property(property="total_price", type="number", format="float", example=65.00),
     *
     *         @OA\Property(
     *           property="created_at",
     *           type="string",
     *           format="date-time",
     *           nullable=true,
     *           example="2026-01-19T21:52:03.379Z"
     *         ),
     *         @OA\Property(
     *           property="updated_at",
     *           type="string",
     *           format="date-time",
     *           nullable=true,
     *           example="2026-01-19T21:55:10.120Z"
     *         ),
     *
     *         @OA\Property(
     *           property="user",
     *           type="object",
     *           nullable=true,
     *           required={"user_id","email","role"},
     *           @OA\Property(property="user_id", type="integer", example=3),
     *           @OA\Property(property="email", type="string", format="email", example="jane@example.com"),
     *           @OA\Property(property="role", type="string", example="user")
     *         ),
     *
     *         @OA\Property(
     *           property="items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             required={"order_item_id","ingredient","amount","total_price"},
     *             @OA\Property(property="order_item_id", type="integer", example=25),
     *             @OA\Property(
     *               property="ingredient",
     *               type="object",
     *               nullable=true,
     *               required={"ingredient_id","name","unit","price"},
     *               @OA\Property(property="ingredient_id", type="integer", example=2),
     *               @OA\Property(property="name", type="string", example="Beli luk"),
     *               @OA\Property(property="unit", type="string", example="kom"),
     *               @OA\Property(property="price", type="number", format="float", example=30.00)
     *             ),
     *             @OA\Property(property="amount", type="integer", example=1),
     *             @OA\Property(property="total_price", type="number", format="float", example=30.00)
     *           )
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Only admins can update orders",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="error", type="string", example="Only admins can update orders")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not Found"
     *   )
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
        $order->load(['user', 'orderItems.ingredient']);

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $this->presentOrder($order),
        ]);
    }
}