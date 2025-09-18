<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ExternalRecipeController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/public/recipes",
     *   tags={"External Recipes"},
     *   summary="Search public recipe APIs (TheMealDB + Spoonacular)",
     *   description="Searches TheMealDB (free) and/or Spoonacular (requires API key) and returns normalized recipe results.",
     *   @OA\Parameter(
     *     name="q", in="query", required=true, description="Search query",
     *     @OA\Schema(type="string", maxLength=100), example="chicken"
     *   ),
     *   @OA\Parameter(
     *     name="source", in="query", required=false, description="Which sources to query",
     *     @OA\Schema(type="string", enum={"mealdb","spoonacular","both"}), example="both"
     *   ),
     *   @OA\Parameter(
     *     name="limit", in="query", required=false, description="Max items per source (1–20, default 10)",
     *     @OA\Schema(type="integer", minimum=1, maximum=20), example=8
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="meta", type="object",
     *         @OA\Property(property="query", type="string", example="salad"),
     *         @OA\Property(property="source", type="string", example="both"),
     *         @OA\Property(property="mealdb_count", type="integer", example=4),
     *         @OA\Property(property="spoonacular_count", type="integer", example=5),
     *         @OA\Property(property="spoonacular_note", type="string", example="Spoonacular API key not configured", nullable=true)
     *       ),
     *       @OA\Property(property="recipes", type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="id", type="string", example="52771"),
     *           @OA\Property(property="title", type="string", example="Greek Salad"),
     *           @OA\Property(property="image", type="string", example="https://.../thumb.jpg"),
     *           @OA\Property(property="source", type="string", example="mealdb"),
     *           @OA\Property(property="source_url", type="string", example="https://..."),
     *           @OA\Property(property="category", type="string", example="Salad", nullable=true),
     *           @OA\Property(property="area", type="string", example="Greek", nullable=true),
     *           @OA\Property(property="instructions", type="string", example="Chop vegetables...", nullable=true),
     *           @OA\Property(property="readyInMinutes", type="integer", example=20, nullable=true),
     *           @OA\Property(property="servings", type="integer", example=2, nullable=true),
     *           @OA\Property(property="summary_html", type="string", example="<b>Delicious</b> salad...", nullable=true),
     *           @OA\Property(property="ingredients", type="array", @OA\Items(type="string"), example={"2 Tomatoes","1 Cucumber","1 tbsp Olive Oil"})
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="No recipes found from selected sources.")
     * )
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q'  => ['required', 'string', 'max:100'],
            'source' => ['sometimes', 'in:mealdb,spoonacular,both'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $q  = trim($validated['q']);
        $source = $validated['source'] ?? 'both';
        $limit = (int)($validated['limit'] ?? 10);

        $results = [];
        $meta = [
            'query' => $q,
            'source' => $source,
        ];

        if ($source === 'mealdb' || $source === 'both') {
            $mealdb = $this->fetchFromMealDb($q, $limit);
            $results = array_merge($results, $mealdb['items']);
            $meta['mealdb_count'] = $mealdb['count'];
        }

        if ($source === 'spoonacular' || $source === 'both') {
            $spoonacular = $this->fetchFromSpoonacular($q, $limit);
            $results = array_merge($results, $spoonacular['items']);
            $meta['spoonacular_count'] = $spoonacular['count'];
            if (!empty($spoonacular['note'])) {
                $meta['spoonacular_note'] = $spoonacular['note'];
            }
        }

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

    protected function fetchFromSpoonacular(string $q, int $limit): array
    {
        $key = config('services.spoonacular.key');
        if (!$key) {
            return [
                'items' => [],
                'count' => 0,
                'note' => 'Spoonacular API key not configured',
            ];
        }

        try {
            $resp = Http::timeout(12)->get('https://api.spoonacular.com/recipes/complexSearch', [
                'apiKey' => $key,
                'query' => $q,
                'number' => $limit,
                'addRecipeInformation' => 'true',
            ]);
            if (!$resp->ok()) {
                return ['items' => [], 'count' => 0];
            }
            $data = $resp->json();
            $results = $data['results'] ?? [];

            $items = [];
            foreach ($results as $r) {
                $ings = [];
                foreach ($r['extendedIngredients'] ?? [] as $ing) {
                    $name = $ing['name'] ?? null;
                    $amount = $ing['measures']['metric']['amount'] ?? null;
                    $unit = $ing['measures']['metric']['unitLong'] ?? null;
                    if ($name) {
                        if ($amount && $unit) {
                            $ings[] = "{$amount} {$unit} {$name}";
                        } else {
                            $ings[] = $name;
                        }
                    }
                }

                $items[] = [
                    'id' => (string)($r['id'] ?? ''),
                    'title' => $r['title'] ?? null,
                    'image' => $r['image'] ?? null,
                    'source' => 'spoonacular',
                    'source_url' => $r['sourceUrl'] ?? null,
                    'readyInMinutes' => $r['readyInMinutes'] ?? null,
                    'servings' => $r['servings'] ?? null,
                    'summary_html' => $r['summary'] ?? null,
                    'ingredients' => $ings,
                ];
            }

            return ['items' => $items, 'count' => count($items)];
        } catch (\Throwable $e) {
            return ['items' => [], 'count' => 0];
        }
    }
}