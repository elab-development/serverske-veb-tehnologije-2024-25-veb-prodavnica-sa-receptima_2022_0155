<?php

namespace App\Http\Controllers;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/cart",
     *   tags={"Cart"},
     *   summary="Get my cart",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"cart_id","user_id","total_amount_of_items","total_price","items"},
     *       @OA\Property(property="cart_id", type="integer", example=11),
     *       @OA\Property(property="user_id", type="integer", example=11),
     *       @OA\Property(property="total_amount_of_items", type="integer", example=2),
     *       @OA\Property(property="total_price", type="number", format="float", example=65.00),
     *       @OA\Property(
     *         property="items",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           required={"cart_item_id","ingredient","amount"},
     *           @OA\Property(property="cart_item_id", type="integer", example=25),
     *           @OA\Property(
     *             property="ingredient",
     *             type="object",
     *             nullable=true,
     *             required={"ingredient_id","name","unit","price","photo_path"},
     *             @OA\Property(property="ingredient_id", type="integer", example=2),
     *             @OA\Property(property="name", type="string", example="Beli luk"),
     *             @OA\Property(property="unit", type="string", example="kom"),
     *             @OA\Property(property="price", type="number", format="float", example=30.00),
     *             @OA\Property(property="photo_path", type="string", example="/images/ingredients/beli_luk.jpg")
     *           ),
     *           @OA\Property(property="amount", type="integer", example=1)
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(response=401, description="Unauthenticated")
     * )
     */

    public function showMyCart()
    {
        $cart = Cart::query()
            ->firstOrCreate(['user_id' => Auth::id()], [
                'total_amount_of_items' => 0,
                'total_price' => 0,
            ]);

        $cart->load('cartItems.ingredient');

        return response()->json($this->presentCart($cart));
    }

    /**
     * @OA\Post(
     *   path="/api/cart/items",
     *   tags={"Cart"},
     *   summary="Add an ingredient to my cart or increase amount",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       type="object",
     *       required={"ingredient_id","amount"},
     *       @OA\Property(property="ingredient_id", type="integer", example=2),
     *       @OA\Property(property="amount", type="integer", minimum=1, example=2)
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=201,
     *     description="Item added",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"message","cart"},
     *       @OA\Property(property="message", type="string", example="Item added to cart"),
     *       @OA\Property(
     *         property="cart",
     *         type="object",
     *         required={"cart_id","user_id","total_amount_of_items","total_price","items"},
     *         @OA\Property(property="cart_id", type="integer", example=11),
     *         @OA\Property(property="user_id", type="integer", example=11),
     *         @OA\Property(property="total_amount_of_items", type="integer", example=3),
     *         @OA\Property(property="total_price", type="number", format="float", example=95.00),
     *         @OA\Property(
     *           property="items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             required={"cart_item_id","ingredient","amount"},
     *             @OA\Property(property="cart_item_id", type="integer", example=25),
     *             @OA\Property(
     *               property="ingredient",
     *               type="object",
     *               nullable=true,
     *               required={"ingredient_id","name","unit","price","photo_path"},
     *               @OA\Property(property="ingredient_id", type="integer", example=2),
     *               @OA\Property(property="name", type="string", example="Beli luk"),
     *               @OA\Property(property="unit", type="string", example="kom"),
     *               @OA\Property(property="price", type="number", format="float", example=30.00),
     *               @OA\Property(property="photo_path", type="string", example="/images/ingredients/beli_luk.jpg")
     *             ),
     *             @OA\Property(property="amount", type="integer", example=2)
     *           )
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="The given data was invalid."),
     *       @OA\Property(property="errors", type="object", example={"ingredient_id":{"The ingredient id field is required."}})
     *     )
     *   )
     * )
     */

    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|integer|exists:ingredients,ingredient_id',
            'amount' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated) {
            $cart = Cart::query()->firstOrCreate(['user_id' => Auth::id()], [
                'total_amount_of_items' => 0,
                'total_price' => 0,
            ]);

            $item = CartItem::query()
                ->where('cart_id', $cart->cart_id)
                ->where('ingredient_id', $validated['ingredient_id'])
                ->first();

            if ($item) {
                $item->update(['amount' => $item->amount + (int)$validated['amount']]);
            } else {
                $item = CartItem::create([
                    'cart_id' => $cart->cart_id,
                    'ingredient_id' => (int)$validated['ingredient_id'],
                    'amount' => (int)$validated['amount'],
                ]);
            }

            $this->recalcCart($cart);

            $cart->load('cartItems.ingredient');

            return response()->json([
                'message' => 'Item added to cart',
                'cart' => $this->presentCart($cart),
            ], 201);
        });
    }

    /**
     * @OA\Post(
     *   path="/api/cart/from-recipes",
     *   tags={"Cart"},
     *   summary="Add ingredients from recipes to my cart -user only",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       type="object",
     *       required={"recipe_ids"},
     *       @OA\Property(
     *         property="recipe_ids",
     *         type="array",
     *         minItems=1,
     *         @OA\Items(type="integer"),
     *         example={1,3,5}
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=201,
     *     description="Recipe ingredients added",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"message","cart"},
     *       @OA\Property(property="message", type="string", example="Recipe ingredients added to cart"),
     *       @OA\Property(
     *         property="cart",
     *         type="object",
     *         required={"cart_id","user_id","total_amount_of_items","total_price","items"},
     *         @OA\Property(property="cart_id", type="integer", example=11),
     *         @OA\Property(property="user_id", type="integer", example=11),
     *         @OA\Property(property="total_amount_of_items", type="integer", example=6),
     *         @OA\Property(property="total_price", type="number", format="float", example=180.00),
     *         @OA\Property(
     *           property="items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             required={"cart_item_id","ingredient","amount"},
     *             @OA\Property(property="cart_item_id", type="integer", example=25),
     *             @OA\Property(
     *               property="ingredient",
     *               type="object",
     *               nullable=true,
     *               required={"ingredient_id","name","unit","price","photo_path"},
     *               @OA\Property(property="ingredient_id", type="integer", example=2),
     *               @OA\Property(property="name", type="string", example="Beli luk"),
     *               @OA\Property(property="unit", type="string", example="kom"),
     *               @OA\Property(property="price", type="number", format="float", example=30.00),
     *               @OA\Property(property="photo_path", type="string", example="/images/ingredients/beli_luk.jpg")
     *             ),
     *             @OA\Property(property="amount", type="integer", example=3)
     *           )
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(
     *     response=403,
     *     description="Only users can add recipe items to cart",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="error", type="string", example="Only users can add recipe items to cart")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error / Empty ingredient set from selected recipes",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="error", type="string", example="Selected recipes resulted in an empty ingredient set")
     *     )
     *   )
     * )
     */

    public function addFromRecipes(Request $request)
    {
        if (Auth::user()->role !== 'user') {
            return response()->json(['error' => 'Only users can add recipe items to cart'], 403);
        }

        $validated = $request->validate([
            'recipe_ids' => ['required', 'array', 'min:1'],
            'recipe_ids.*' => ['integer', 'distinct', 'exists:recipes,recipe_id'],
        ]);

        $baseMap = [];

        $recipes = Recipe::query()
            ->whereIn('recipe_id', $validated['recipe_ids'])
            ->with('recipeItems') 
            ->get();

        foreach ($recipes as $r) {
            foreach ($r->recipeItems as $ri) {
                $iid = (int) $ri->ingredient_id;
                $qty = (int) $ri->quantity;
                $baseMap[$iid] = ($baseMap[$iid] ?? 0) + max(1, $qty);
            }
        }

        if (empty($baseMap)) {
            return response()->json(['error' => 'Selected recipes resulted in an empty ingredient set'], 422);
        }

        return DB::transaction(function () use ($baseMap) {

            $cart = Cart::query()->firstOrCreate(['user_id' => Auth::id()], [
                'total_amount_of_items' => 0,
                'total_price' => 0,
            ]);

            $ingredientIds = array_keys($baseMap);

            $existing = CartItem::query()
                ->where('cart_id', $cart->cart_id)
                ->whereIn('ingredient_id', $ingredientIds)
                ->get()
                ->keyBy('ingredient_id');

            foreach ($baseMap as $ingredientId => $amountToAdd) {
                $ingredientId = (int) $ingredientId;
                $amountToAdd = (int) $amountToAdd;

                if ($existing->has($ingredientId)) {
                    $item = $existing[$ingredientId];
                    $item->update(['amount' => (int)$item->amount + $amountToAdd]);
                } else {
                    CartItem::create([
                        'cart_id' => $cart->cart_id,
                        'ingredient_id' => $ingredientId,
                        'amount' => $amountToAdd,
                    ]);
                }
            }

            $this->recalcCart($cart);

            $cart->load('cartItems.ingredient');

            return response()->json([
                'message' => 'Recipe ingredients added to cart',
                'cart' => $this->presentCart($cart),
            ], 201);
        });
    }

    /**
     * @OA\Put(
     *   path="/api/cart/items/{cartItem}",
     *   tags={"Cart"},
     *   summary="Update cart item amount- owner only",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="cartItem",
     *     in="path",
     *     required=true,
     *     description="Cart item ID",
     *     @OA\Schema(type="integer", example=25)
     *   ),
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       type="object",
     *       required={"amount"},
     *       @OA\Property(property="amount", type="integer", minimum=1, example=3)
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Cart item updated",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"message","cart"},
     *       @OA\Property(property="message", type="string", example="Cart item updated"),
     *       @OA\Property(
     *         property="cart",
     *         type="object",
     *         required={"cart_id","user_id","total_amount_of_items","total_price","items"},
     *         @OA\Property(property="cart_id", type="integer", example=11),
     *         @OA\Property(property="user_id", type="integer", example=11),
     *         @OA\Property(property="total_amount_of_items", type="integer", example=3),
     *         @OA\Property(property="total_price", type="number", format="float", example=95.00),
     *         @OA\Property(
     *           property="items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             required={"cart_item_id","ingredient","amount"},
     *             @OA\Property(property="cart_item_id", type="integer", example=25),
     *             @OA\Property(
     *               property="ingredient",
     *               type="object",
     *               nullable=true,
     *               required={"ingredient_id","name","unit","price","photo_path"},
     *               @OA\Property(property="ingredient_id", type="integer", example=2),
     *               @OA\Property(property="name", type="string", example="Beli luk"),
     *               @OA\Property(property="unit", type="string", example="kom"),
     *               @OA\Property(property="price", type="number", format="float", example=30.00),
     *               @OA\Property(property="photo_path", type="string", example="/images/ingredients/beli_luk.jpg")
     *             ),
     *             @OA\Property(property="amount", type="integer", example=3)
     *           )
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(response=401, description="Unauthenticated"),
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
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="The given data was invalid."),
     *       @OA\Property(property="errors", type="object", example={"amount":{"The amount field must be at least 1."}})
     *     )
     *   )
     * )
     */

    public function updateItem(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
        ]);

        $cart = Cart::query()->where('cart_id', $cartItem->cart_id)->firstOrFail();

        if ((int)$cart->user_id !== (int)Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return DB::transaction(function () use ($cart, $cartItem, $validated) {
            $cartItem->update(['amount' => (int)$validated['amount']]);

            $this->recalcCart($cart);

            $cart->load('cartItems.ingredient');

            return response()->json([
                'message' => 'Cart item updated',
                'cart' => $this->presentCart($cart),
            ]);
        });
    }

    /**
     * @OA\Delete(
     *   path="/api/cart/items/{cartItem}",
     *   tags={"Cart"},
     *   summary="Remove a cart item - owner only",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="cartItem",
     *     in="path",
     *     required=true,
     *     description="Cart item ID",
     *     @OA\Schema(type="integer", example=25)
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Cart item removed",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"message","cart"},
     *       @OA\Property(property="message", type="string", example="Cart item removed"),
     *       @OA\Property(
     *         property="cart",
     *         type="object",
     *         required={"cart_id","user_id","total_amount_of_items","total_price","items"},
     *         @OA\Property(property="cart_id", type="integer", example=11),
     *         @OA\Property(property="user_id", type="integer", example=11),
     *         @OA\Property(property="total_amount_of_items", type="integer", example=2),
     *         @OA\Property(property="total_price", type="number", format="float", example=65.00),
     *         @OA\Property(
     *           property="items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             required={"cart_item_id","ingredient","amount"},
     *             @OA\Property(property="cart_item_id", type="integer", example=26),
     *             @OA\Property(
     *               property="ingredient",
     *               type="object",
     *               nullable=true,
     *               required={"ingredient_id","name","unit","price","photo_path"},
     *               @OA\Property(property="ingredient_id", type="integer", example=26),
     *               @OA\Property(property="name", type="string", example="Biber"),
     *               @OA\Property(property="unit", type="string", example="100g"),
     *               @OA\Property(property="price", type="number", format="float", example=35.00),
     *               @OA\Property(property="photo_path", type="string", example="/images/ingredients/biber.jpg")
     *             ),
     *             @OA\Property(property="amount", type="integer", example=1)
     *           )
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="error", type="string", example="Forbidden")
     *     )
     *   ),
     *   @OA\Response(response=404, description="Not Found")
     * )
     */

    public function removeItem(CartItem $cartItem)
    {
        $cart = Cart::query()->where('cart_id', $cartItem->cart_id)->firstOrFail();

        if ((int)$cart->user_id !== (int)Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return DB::transaction(function () use ($cart, $cartItem) {
            $cartItem->delete();

            $this->recalcCart($cart);

            $cart->load('cartItems.ingredient');

            return response()->json([
                'message' => 'Cart item removed',
                'cart' => $this->presentCart($cart),
            ]);
        });
    }

    /**
     * @OA\Post(
     *   path="/api/cart/checkout",
     *   tags={"Cart"},
     *   summary="Checkout my cart and create an order",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Response(
     *     response=201,
     *     description="Checkout successful",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"message","order"},
     *       @OA\Property(property="message", type="string", example="Checkout successful"),
     *       @OA\Property(
     *         property="order",
     *         type="object",
     *         required={"order_id","status","total_price","items"},
     *         @OA\Property(property="order_id", type="integer", example=31),
     *         @OA\Property(property="status", type="string", example="pending"),
     *         @OA\Property(property="total_price", type="number", format="float", example=65.00),
     *         @OA\Property(
     *           property="items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             required={"ingredient_id","amount","total_price"},
     *             @OA\Property(property="ingredient_id", type="integer", example=2),
     *             @OA\Property(property="amount", type="integer", example=1),
     *             @OA\Property(property="total_price", type="number", format="float", example=30.00)
     *           )
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(
     *     response=404,
     *     description="Cart not found",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="error", type="string", example="Cart not found")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Cart is empty",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="error", type="string", example="Cart is empty")
     *     )
     *   )
     * )
     */

    public function checkout()
    {
        $cart = Cart::query()->where('user_id', Auth::id())->first();

        if (!$cart) {
            return response()->json(['error' => 'Cart not found'], 404);
        }

        $cart->load('cartItems.ingredient');

        if ($cart->cartItems->isEmpty()) {
            return response()->json(['error' => 'Cart is empty'], 422);
        }

        return DB::transaction(function () use ($cart) {
            $order = Order::create([
                'user_id' => Auth::id(),
                'status' => 'pending',
                'total_price' => 0,
            ]);

            $total = 0.0;

            foreach ($cart->cartItems as $ci) {
                $ing = $ci->ingredient;
                $line = (float)$ing->price * (int)$ci->amount;
                $total += $line;

                OrderItem::create([
                    'order_id' => $order->order_id,
                    'user_id' => Auth::id(),
                    'ingredient_id' => $ing->ingredient_id,
                    'amount' => (int)$ci->amount,
                    'total_price' => number_format($line, 2, '.', ''),
                ]);
            }

            $order->update(['total_price' => number_format($total, 2, '.', '')]);

            CartItem::where('cart_id', $cart->cart_id)->delete();
            $cart->update(['total_amount_of_items' => 0, 'total_price' => 0]);

            $order->load(['user', 'orderItems.ingredient']);

            return response()->json([
                'message' => 'Checkout successful',
                'order' => [
                    'order_id' => $order->order_id,
                    'status' => $order->status,
                    'total_price' => (float)$order->total_price,
                    'items' => $order->orderItems->map(fn($it) => [
                        'ingredient_id' => $it->ingredient_id,
                        'amount' => (int)$it->amount,
                        'total_price' => (float)$it->total_price,
                    ])->values(),
                ],
            ], 201);
        });
    }

    private function recalcCart(Cart $cart): void
    {
        $items = CartItem::query()
            ->where('cart_id', $cart->cart_id)
            ->get();

        $sumAmount = 0;
        $sumPrice = 0.0;

        if ($items->isNotEmpty()) {
            $ingredients = Ingredient::query()
                ->whereIn('ingredient_id', $items->pluck('ingredient_id')->all())
                ->get()
                ->keyBy('ingredient_id');

            foreach ($items as $it) {
                $sumAmount += (int)$it->amount;
                $sumPrice += (float)$ingredients[$it->ingredient_id]->price * (int)$it->amount;
            }
        }

        $cart->update([
            'total_amount_of_items' => $sumAmount,
            'total_price' => number_format($sumPrice, 2, '.', ''),
        ]);
    }

    private function presentCart(Cart $cart): array
    {
        return [
            'cart_id' => $cart->cart_id,
            'user_id' => $cart->user_id,
            'total_amount_of_items' => (int)$cart->total_amount_of_items,
            'total_price' => (float)$cart->total_price,
            'items' => $cart->cartItems->map(function ($it) {
                return [
                    'cart_item_id' => $it->cart_item_id,
                    'ingredient' => $it->ingredient ? [
                        'ingredient_id' => $it->ingredient->ingredient_id,
                        'name' => $it->ingredient->name,
                        'unit' => $it->ingredient->unit,
                        'price' => (float)$it->ingredient->price,
                        'photo_path' => $it->ingredient->photo_path,
                    ] : null,
                    'amount' => (int)$it->amount,
                ];
            })->values(),
        ];
    }
}
