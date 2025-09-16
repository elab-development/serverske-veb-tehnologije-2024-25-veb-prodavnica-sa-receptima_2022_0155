<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ExternalRecipeController extends Controller
{
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q'  => ['required', 'string', 'max:100'],
            'source' => ['sometimes', 'in:mealdb'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $q  = trim($validated['q']);
        $source = $validated['source'];
        $limit = (int)($validated['limit'] ?? 10);

        $results = [];
        $meta = [
            'query' => $q,
            'source' => $source,
        ];

        $mealdb = $this->fetchFromMealDb($q, $limit);
        $results = array_merge($results, $mealdb['items']);
        $meta['mealdb_count'] = $mealdb['count'];
        

        if (empty($results)) {
            return response()->json('No recipes found from selected sources.', 404);
        }

        return response()->json([
            'meta' => $meta,
            'recipes' => $results,
        ]);
    }

    protected function fetchFromMealDb(string $q, int $limit): array
    {
        try {
            $resp = Http::timeout(10)->get('https://www.themealdb.com/api/json/v1/1/search.php', [
                's' => $q,
            ]);
            if (!$resp->ok()) {
                return ['items' => [], 'count' => 0];
            }
            $payload = $resp->json();
            $meals = $payload['meals'] ?? [];

            $items = [];
            foreach (array_slice($meals, 0, $limit) as $m) {
                $ingredients = [];
                for ($i = 1; $i <= 20; $i++) {
                    $ing = $m["strIngredient{$i}"] ?? null;
                    $meas = $m["strMeasure{$i}"] ?? null;
                    if ($ing && trim($ing) !== '') {
                        $label = trim($ing);
                        if ($meas && trim($meas) !== '') {
                            $label = trim($meas) . ' ' . $label;
                        }
                        $ingredients[] = $label;
                    }
                }

                $items[] = [
                    'id' => (string)($m['idMeal'] ?? ''),
                    'title' => $m['strMeal'] ?? null,
                    'image' => $m['strMealThumb'] ?? null,
                    'source' => 'mealdb',
                    'source_url' => $m['strSource'] ?? null,
                    'category' => $m['strCategory'] ?? null,
                    'area' => $m['strArea'] ?? null,
                    'instructions' => $m['strInstructions'] ?? null,
                    'ingredients' => $ingredients,
                ];
            }

            return ['items' => $items, 'count' => count($items)];
        } catch (\Throwable $e) {
            return ['items' => [], 'count' => 0];
        }
    }

   
}