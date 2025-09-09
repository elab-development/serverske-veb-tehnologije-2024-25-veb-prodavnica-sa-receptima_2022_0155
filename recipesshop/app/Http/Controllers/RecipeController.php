<?php

namespace App\Http\Controllers;

use App\Http\Resources\RecipeResource;
use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RecipeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $v = Validator::make($request->all(), [
            'search' => ['sometimes', 'string', 'max:200'],
            'ingredients_any' => ['sometimes', 'string'],
            'ingredients_all' => ['sometimes', 'string'],
            'ingredients_exclude' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'string', 'in:name,-name,created_at,-created_at,updated_at,-updated_at,ingredients_count,-ingredients_count'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $v->validate();

        $perPage = (int) $request->input('per_page', 15);
        $page    = (int) $request->input('page', 1);
        $search  = trim((string) $request->input('search', ''));
        $sort    = (string) $request->input('sort', 'name');

        $parseIds = fn($csv) => array_values(array_unique(
            array_filter(array_map('intval', explode(',', (string) $csv)), fn($i) => $i > 0)
        ));

        $idsAny = $parseIds($request->input('ingredients_any'));
        $idsAll = $parseIds($request->input('ingredients_all'));
        $idsExclude = $parseIds($request->input('ingredients_exclude'));

        $q = Recipe::query();

        if ($search !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $search);

            $matchedIngredientIds = Ingredient::query()
                ->where('name', 'like', "%{$escaped}%")
                ->pluck('id')
                ->all();

            $q->where(function ($w) use ($search, $matchedIngredientIds) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");

                if (!empty($matchedIngredientIds)) {
                    $w->orWhere(function ($ww) use ($matchedIngredientIds) {
                        foreach ($matchedIngredientIds as $id) {
                            $ww->orWhereRaw('JSON_CONTAINS(ingredient_ids, ?)', [json_encode($id)]);
                        }
                    });
                }
            });
        }

        if (!empty($idsAny)) {
            $q->where(function ($w) use ($idsAny) {
                foreach ($idsAny as $id) {
                    $w->orWhereRaw('JSON_CONTAINS(ingredient_ids, ?)', [json_encode($id)]);
                }
            });
        }

        if (!empty($idsAll)) {
            foreach ($idsAll as $id) {
                $q->whereRaw('JSON_CONTAINS(ingredient_ids, ?)', [json_encode($id)]);
            }
        }

        if (!empty($idsExclude)) {
            foreach ($idsExclude as $id) {
                $q->whereRaw('NOT JSON_CONTAINS(ingredient_ids, ?)', [json_encode($id)]);
            }
        }

        $direction = Str::startsWith($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        switch ($field) {
            case 'name':
            case 'created_at':
            case 'updated_at':
                $q->orderBy($field, $direction);
                break;
            case 'ingredients_count':
                $q->orderByRaw('JSON_LENGTH(ingredient_ids) ' . $direction);
                break;
            default:
                $q->orderBy('name', 'asc');
        }

        $recipes = $q->paginate($perPage, ['*'], 'page', $page);

        if ($recipes->isEmpty()) {
            return response()->json('No recipes found.', 404);
        }

        return response()->json([
            'meta'    => [
                'page' => $recipes->currentPage(),
                'per_page' => $recipes->perPage(),
                'total' => $recipes->total(),
                'last_page' => $recipes->lastPage(),
            ],
            'recipes' => RecipeResource::collection($recipes),
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
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can create recipes'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:recipes,name',
            'description' => 'nullable|string',
            'ingredient_ids' => 'required|array|min:1',
            'ingredient_ids.*' => 'integer|distinct|exists:ingredients,id',
        ]);

        $validated['ingredient_ids'] = array_values(array_unique($validated['ingredient_ids']));

        $recipe = Recipe::create($validated);

        return response()->json([
            'message' => 'Recipe created successfully',
            'recipe' => new RecipeResource($recipe),
        ], 201);
    }
    /**
     * Display the specified resource.
     */
    public function show(Recipe $recipe)
    {
        return response()->json([
            'recipe' => new RecipeResource($recipe),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Recipe $recipe)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Recipe $recipe)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can update recipes'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:recipes,name,' . $recipe->id,
            'description' => 'sometimes|nullable|string',
            'ingredient_ids' => 'sometimes|array|min:1',
            'ingredient_ids.*' => 'integer|distinct|exists:ingredients,id',
        ]);

        if (isset($validated['ingredient_ids'])) {
            $validated['ingredient_ids'] = array_values(array_unique($validated['ingredient_ids']));
        }

        $recipe->update($validated);

        return response()->json([
            'message' => 'Recipe updated successfully',
            'recipe' => new RecipeResource($recipe),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Recipe $recipe)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can delete recipes'], 403);
        }

        $recipe->delete();

        return response()->json(['message' => 'Recipe deleted successfully']);
    }
}
