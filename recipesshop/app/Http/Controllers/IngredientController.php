<?php

namespace App\Http\Controllers;

use App\Http\Resources\IngredientResource;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IngredientController extends Controller
{
    /**
     * Display a listing of the resource.
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
     * Display the specified resource.
     */
    public function show(Ingredient $ingredient)
    {
        return response()->json([
            'ingredient' => new IngredientResource($ingredient),
        ]);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ingredient $ingredient)
    {
        //
    }

    /**
     * Update the specified resource in storage.
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
     * Remove the specified resource from storage.
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
