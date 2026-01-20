<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExternalRecipeController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\RecipeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CartController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/ingredients', [IngredientController::class, 'index']);
Route::get('/ingredients/{ingredient}', [IngredientController::class, 'show']);

Route::get('/recipes', [RecipeController::class, 'index']);
Route::get('/recipes/{recipe}/ingredients', [RecipeController::class, 'ingredients']);
Route::get('/recipes/{recipe}', [RecipeController::class, 'show']);

Route::get('/public/recipes', [ExternalRecipeController::class, 'search']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/cart', [CartController::class, 'showMyCart']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::put('/cart/items/{cartItem}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem']);
    Route::post('/cart/checkout', [CartController::class, 'checkout']); 
    Route::post('/cart/from-recipes', [CartController::class, 'addFromRecipes']);
});

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/logout', [AuthController::class, 'logout']);

     Route::resource('ingredients', IngredientController::class)
        ->only(['store', 'update', 'destroy']);

    Route::resource('recipes', RecipeController::class)
        ->only(['store', 'update', 'destroy']);
    
    Route::resource('orders', OrderController::class)
        ->except(['edit', 'create', 'destroy']);
    Route::get('/users/{user}/orders', [OrderController::class, 'forUser']);
});