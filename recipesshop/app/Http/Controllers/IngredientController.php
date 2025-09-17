<?php

namespace App\Http\Controllers;

use App\Http\Resources\IngredientResource;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IngredientController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/ingredients",
     *   tags={"Ingredients"},
     *   summary="List all ingredients",
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="ingredients",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=1),
     *           @OA\Property(property="name", type="string", example="Tomato"),
     *           @OA\Property(property="price", type="number", format="float", example=1.2)
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="No ingredients found.")
     * )
     */
    public function index()
    {
        $ingredients = Ingredient::orderBy('name')->get();

        if ($ingredients->isEmpty()) {
            return response()->json('No ingredients found.', 404);
        }

        return response()->json([
            'ingredients' => IngredientResource::collection($ingredients),
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/ingredients",
     *   tags={"Ingredients"},
     *   summary="Create a new ingredient (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","price"},
     *       @OA\Property(property="name", type="string", maxLength=255, example="Olive Oil"),
     *       @OA\Property(property="price", type="number", format="float", example=5.50)
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Ingredient created",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Ingredient created successfully"),
     *       @OA\Property(property="ingredient",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=20),
     *         @OA\Property(property="name", type="string", example="Olive Oil"),
     *         @OA\Property(property="price", type="number", format="float", example=5.50)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can create ingredients"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can create ingredients'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ingredients,name',
            'price' => 'required|numeric|min:0',
        ]);

        $ingredient = Ingredient::create($validated);

        return response()->json([
            'message' => 'Ingredient created successfully',
            'ingredient' => new IngredientResource($ingredient),
        ], 201);
    }

    /**
     * @OA\Get(
     *   path="/api/ingredients/{ingredient}",
     *   tags={"Ingredients"},
     *   summary="Get a single ingredient",
     *   @OA\Parameter(
     *     name="ingredient",
     *     in="path",
     *     required=true,
     *     description="Ingredient ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="ingredient",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=5),
     *         @OA\Property(property="name", type="string", example="Cheese"),
     *         @OA\Property(property="price", type="number", format="float", example=3.20)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="Ingredient not found")
     * )
     */
    public function show(Ingredient $ingredient)
    {
        return response()->json([
            'ingredient' => new IngredientResource($ingredient),
        ]);
    }

    /**
     * @OA\Put(
     *   path="/api/ingredients/{id}",
     *   tags={"Ingredients"},
     *   summary="Update an ingredient (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id", in="path", required=true, description="Ingredient ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", maxLength=255, example="Greek Olive Oil"),
     *       @OA\Property(property="price", type="number", format="float", example=5.99)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Ingredient updated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Ingredient updated successfully"),
     *       @OA\Property(property="ingredient",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=20),
     *         @OA\Property(property="name", type="string", example="Greek Olive Oil"),
     *         @OA\Property(property="price", type="number", format="float", example=5.99)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can update ingredients"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Ingredient $ingredient)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can update ingredients'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:ingredients,name,' . $ingredient->id,
            'price' => 'sometimes|numeric|min:0',
        ]);

        $ingredient->update($validated);

        return response()->json([
            'message' => 'Ingredient updated successfully',
            'ingredient' => new IngredientResource($ingredient),
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/api/ingredients/{id}",
     *   tags={"Ingredients"},
     *   summary="Delete an ingredient (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id", in="path", required=true, description="Ingredient ID",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Ingredient deleted",
     *     @OA\JsonContent(type="object", example={"message":"Ingredient deleted successfully"})
     *   ),
     *   @OA\Response(response=403, description="Only admins can delete ingredients")
     * )
     */
    public function destroy(Ingredient $ingredient)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can delete ingredients'], 403);
        }

        $ingredient->delete();

        return response()->json(['message' => 'Ingredient deleted successfully']);
    }
}
