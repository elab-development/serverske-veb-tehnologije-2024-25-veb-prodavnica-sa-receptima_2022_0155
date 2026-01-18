<?php

namespace App\Http\Controllers;

use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\RecipeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecipeController extends Controller
{
     /**
     * @OA\Get(
     *   path="/api/recipes",
     *   tags={"Recipes"},
     *   summary="List recipes (search, filters, sort, pagination)",
     *   description="Returns paginated recipes. Supports searching in recipe name/description and ingredient names via relationship.",
     *   @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=200), description="Search in name, description, and ingredient names"),
     *   @OA\Parameter(name="ingredients_any", in="query", required=false, @OA\Schema(type="string"), description="CSV of ingredient_ids; recipe must contain ANY of them"),
     *   @OA\Parameter(name="ingredients_all", in="query", required=false, @OA\Schema(type="string"), description="CSV of ingredient_ids; recipe must contain ALL of them"),
     *   @OA\Parameter(name="ingredients_exclude", in="query", required=false, @OA\Schema(type="string"), description="CSV of ingredient_ids; recipe must NOT contain any of them"),
     *   @OA\Parameter(
     *     name="sort", in="query", required=false,
     *     @OA\Schema(type="string", enum={"name","-name","created_at","-created_at","updated_at","-updated_at","ingredients_count","-ingredients_count"}),
     *     description="Sort field (prefix with - for DESC)"
     *   ),
     *   @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100), description="Items per page (default 15)"),
     *   @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1), description="Page number"),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="meta", type="object",
     *         @OA\Property(property="page", type="integer", example=1),
     *         @OA\Property(property="per_page", type="integer", example=15),
     *         @OA\Property(property="total", type="integer", example=42),
     *         @OA\Property(property="last_page", type="integer", example=3)
     *       ),
     *       @OA\Property(property="recipes", type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="recipe_id", type="integer", example=7),
     *           @OA\Property(property="name", type="string", example="Greek Salad"),
     *           @OA\Property(property="description", type="string", example="Fresh and easy."),
     *           @OA\Property(property="ingredients_count", type="integer", example=4),
     *           @OA\Property(property="ingredients", type="array",
     *             @OA\Items(type="object",
     *               @OA\Property(property="ingredient_id", type="integer", example=1),
     *               @OA\Property(property="name", type="string", example="Tomato"),
     *               @OA\Property(property="price", type="number", format="float", example=1.20),
     *               @OA\Property(property="unit", type="string", example="kg"),
     *               @OA\Property(property="quantity", type="integer", example=2, description="Pivot quantity (recipe_items.quantity)")
     *             )
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
     public function index(Request $request)
    {
        $request->validate([
            'search' => ['sometimes', 'string', 'max:200'],
            'ingredients_any' => ['sometimes', 'string'],
            'ingredients_all' => ['sometimes', 'string'],
            'ingredients_exclude' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'string', 'in:name,-name,created_at,-created_at,updated_at,-updated_at,ingredients_count,-ingredients_count'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $sort    = (string) $request->input('sort', 'name');
        $search  = trim((string) $request->input('search', ''));

        $parseIds = fn($csv) => array_values(array_unique(
            array_filter(array_map('intval', explode(',', (string) $csv)), fn($i) => $i > 0)
        ));

        $idsAny = $parseIds($request->input('ingredients_any'));
        $idsAll = $parseIds($request->input('ingredients_all'));
        $idsExclude = $parseIds($request->input('ingredients_exclude'));

        $q = Recipe::query()->with(['ingredients']); 

        if ($search !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $search);

            $q->where(function ($w) use ($escaped) {
                $w->where('name', 'like', "%{$escaped}%")
                  ->orWhere('description', 'like', "%{$escaped}%")
                  ->orWhereHas('ingredients', function ($qi) use ($escaped) {
                      $qi->where('name', 'like', "%{$escaped}%");
                  });
            });
        }

        if (!empty($idsAny)) {
            $q->whereHas('ingredients', fn($qi) => $qi->whereIn('ingredients.ingredient_id', $idsAny));
        }

        if (!empty($idsAll)) {
            foreach ($idsAll as $id) {
                $q->whereHas('ingredients', fn($qi) => $qi->where('ingredients.ingredient_id', $id));
            }
        }

        if (!empty($idsExclude)) {
            $q->whereDoesntHave('ingredients', fn($qi) => $qi->whereIn('ingredients.ingredient_id', $idsExclude));
        }

        $q->withCount('ingredients');

        $direction = Str::startsWith($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        if (in_array($field, ['name', 'created_at', 'updated_at'], true)) {
            $q->orderBy($field, $direction);
        } elseif ($field === 'ingredients_count') {
            $q->orderBy('ingredients_count', $direction);
        } else {
            $q->orderBy('name', 'asc');
        }

        $recipes = $q->paginate($perPage);

        return response()->json([
            'meta'    => [
                'page' => $recipes->currentPage(),
                'per_page' => $recipes->perPage(),
                'total' => $recipes->total(),
                'last_page' => $recipes->lastPage(),
            ],
            'recipes' => RecipeResource::collection($recipes->getCollection()),
        ], 200);
    }
    /**
     * @OA\Get(
     *   path="/api/recipes/{recipe}/ingredients",
     *   tags={"Recipes"},
     *   summary="Get ingredients for a recipe (with quantity)",
     *   @OA\Parameter(
     *     name="recipe", in="path", required=true, description="Recipe ID (recipe_id)",
     *     @OA\Schema(type="integer", example=7)
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="recipe_id", type="integer", example=7),
     *       @OA\Property(property="ingredients", type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="ingredient_id", type="integer", example=1),
     *           @OA\Property(property="name", type="string", example="Tomato"),
     *           @OA\Property(property="price", type="number", format="float", example=1.20),
     *           @OA\Property(property="unit", type="string", example="kg"),
     *           @OA\Property(property="quantity", type="integer", example=2)
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="Recipe not found")
     * )
     */
    public function ingredients(Recipe $recipe)
    {
        $recipe->load('ingredients');

        return response()->json([
            'recipe_id' => $recipe->recipe_id,
            'ingredients' => $recipe->ingredients->map(function ($ing) {
                return [
                    'ingredient_id' => $ing->ingredient_id,
                    'name' => $ing->name,
                    'price' => (float) $ing->price,
                    'unit' => $ing->unit,
                    'quantity' => (int) ($ing->pivot->quantity ?? 1),
                ];
            })->values(),
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * @OA\Post(
     *   path="/api/recipes",
     *   tags={"Recipes"},
     *   summary="Create a new recipe (admin only)",
     *   security={{"bearerAuth":{}}},
     *   description="You can send either items[] (ingredient_id + quantity) OR ingredient_ids[] (quantity defaults to 1).",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name"},
     *       oneOf={
     *         @OA\Schema(
     *           required={"name","items"},
     *           @OA\Property(property="name", type="string", maxLength=255, example="Greek Salad"),
     *           @OA\Property(property="description", type="string", nullable=true, example="Fresh and easy."),
     *           @OA\Property(
     *             property="items",
     *             type="array",
     *             @OA\Items(
     *               type="object",
     *               required={"ingredient_id","quantity"},
     *               @OA\Property(property="ingredient_id", type="integer", example=1),
     *               @OA\Property(property="quantity", type="integer", minimum=1, example=2)
     *             )
     *           )
     *         ),
     *         @OA\Schema(
     *           required={"name","ingredient_ids"},
     *           @OA\Property(property="name", type="string", maxLength=255, example="Simple Salad"),
     *           @OA\Property(property="description", type="string", nullable=true, example="No quantities provided."),
     *           @OA\Property(property="ingredient_ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Recipe created",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Recipe created successfully"),
     *       @OA\Property(property="recipe", type="object")
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can create recipes"),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Only admins can create recipes'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:recipes,name',
            'description' => 'nullable|string',

            'items' => 'sometimes|array|min:1',
            'items.*.ingredient_id' => 'required_with:items|integer|distinct|exists:ingredients,ingredient_id',
            'items.*.quantity' => 'required_with:items|integer|min:1',

            'ingredient_ids' => 'sometimes|array|min:1',
            'ingredient_ids.*' => 'integer|distinct|exists:ingredients,ingredient_id',
        ]);

        $items = $this->normalizeRecipeItems($request);

        return DB::transaction(function () use ($validated, $items) {
            $recipe = Recipe::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            foreach ($items as $row) {
                RecipeItem::create([
                    'recipe_id' => $recipe->recipe_id,
                    'ingredient_id' => $row['ingredient_id'],
                    'quantity' => $row['quantity'],
                ]);
            }

            $recipe->load('ingredients');

            return response()->json([
                'message' => 'Recipe created successfully',
                'recipe' => new RecipeResource($recipe),
            ], 201);
        });
    }
    /**
     * @OA\Get(
     *   path="/api/recipes/{recipe}",
     *   tags={"Recipes"},
     *   summary="Get a single recipe",
     *   @OA\Parameter(
     *     name="recipe", in="path", required=true, description="Recipe ID (recipe_id)",
     *     @OA\Schema(type="integer", example=7)
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="recipe", type="object",
     *         @OA\Property(property="recipe_id", type="integer", example=7),
     *         @OA\Property(property="name", type="string", example="Greek Salad"),
     *         @OA\Property(property="description", type="string", example="Fresh and easy."),
     *         @OA\Property(property="ingredients", type="array",
     *           @OA\Items(type="object",
     *             @OA\Property(property="ingredient_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Tomato"),
     *             @OA\Property(property="price", type="number", format="float", example=1.20),
     *             @OA\Property(property="unit", type="string", example="kg"),
     *             @OA\Property(property="quantity", type="integer", example=2)
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="Recipe not found")
     * )
     */
    public function show(Recipe $recipe)
    {
        $recipe->load('ingredients');

        return response()->json([
            'recipe' => new RecipeResource($recipe),
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Recipe $recipe)
    {
        //
    }

    /**
     * @OA\Put(
     *   path="/api/recipes/{recipe}",
     *   tags={"Recipes"},
     *   summary="Update a recipe (admin only)",
     *   security={{"bearerAuth":{}}},
     *   description="If items or ingredient_ids are provided, existing recipe_items are deleted and replaced with new ones.",
     *   @OA\Parameter(
     *     name="recipe", in="path", required=true, description="Recipe ID (recipe_id)",
     *     @OA\Schema(type="integer", example=7)
     *   ),
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", maxLength=255, example="Updated Salad"),
     *       @OA\Property(property="description", type="string", nullable=true, example="Updated description."),
     *       @OA\Property(
     *         property="items",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           required={"ingredient_id","quantity"},
     *           @OA\Property(property="ingredient_id", type="integer", example=1),
     *           @OA\Property(property="quantity", type="integer", minimum=1, example=3)
     *         )
     *       ),
     *       @OA\Property(property="ingredient_ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Recipe updated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Recipe updated successfully"),
     *       @OA\Property(property="recipe", type="object")
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can update recipes"),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=404, description="Recipe not found")
     * )
     */
    public function update(Request $request, Recipe $recipe)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Only admins can update recipes'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:recipes,name,' . $recipe->recipe_id . ',recipe_id',
            'description' => 'sometimes|nullable|string',

            'items' => 'sometimes|array|min:1',
            'items.*.ingredient_id' => 'required_with:items|integer|distinct|exists:ingredients,ingredient_id',
            'items.*.quantity' => 'required_with:items|integer|min:1',

            'ingredient_ids' => 'sometimes|array|min:1',
            'ingredient_ids.*' => 'integer|distinct|exists:ingredients,ingredient_id',
        ]);

        $hasItems = $request->has('items') || $request->has('ingredient_ids');

        return DB::transaction(function () use ($recipe, $validated, $hasItems, $request) {
            $recipe->update([
                'name' => $validated['name'] ?? $recipe->name,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $recipe->description,
            ]);

            if ($hasItems) {
                $items = $this->normalizeRecipeItems($request);

                RecipeItem::where('recipe_id', $recipe->recipe_id)->delete();

                foreach ($items as $row) {
                    RecipeItem::create([
                        'recipe_id' => $recipe->recipe_id,
                        'ingredient_id' => $row['ingredient_id'],
                        'quantity' => $row['quantity'],
                    ]);
                }
            }

            $recipe->load('ingredients');

            return response()->json([
                'message' => 'Recipe updated successfully',
                'recipe' => new RecipeResource($recipe),
            ], 200);
        });
    }

    /**
     * @OA\Delete(
     *   path="/api/recipes/{recipe}",
     *   tags={"Recipes"},
     *   summary="Delete a recipe (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="recipe", in="path", required=true, description="Recipe ID (recipe_id)",
     *     @OA\Schema(type="integer", example=7)
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Recipe deleted",
     *     @OA\JsonContent(type="object", example={"message":"Recipe deleted successfully"})
     *   ),
     *   @OA\Response(response=403, description="Only admins can delete recipes"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=404, description="Recipe not found")
     * )
     */
    public function destroy(Recipe $recipe)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Only admins can delete recipes'], 403);
        }

        $recipe->delete();

        return response()->json(['message' => 'Recipe deleted successfully'], 200);
    }

    private function normalizeRecipeItems(Request $request): array
    {
        if ($request->has('items')) {
            $items = $request->input('items', []);
            return collect($items)
                ->map(fn($x) => [
                    'ingredient_id' => (int) $x['ingredient_id'],
                    'quantity' => (int) $x['quantity'],
                ])
                ->values()
                ->all();
        }

        $ids = $request->input('ingredient_ids', []);
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return array_map(fn($id) => ['ingredient_id' => $id, 'quantity' => 1], $ids);
    }
}