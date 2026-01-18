<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
     /**
 * @OA\Post(
 *   path="/api/register",
 *   tags={"Auth"},
 *   summary="Register a new user",
 *   description="Creates a new user and returns a Sanctum access token.",
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *       required={"name","email","password"},
 *       @OA\Property(property="name", type="string", maxLength=255, example="Jane Doe"),
 *       @OA\Property(property="email", type="string", format="email", example="jane@example.com"),
 *       @OA\Property(property="password", type="string", minLength=8, example="secret1234")
 *     )
 *   ),
 *   @OA\Response(
 *     response=201,
 *     description="User created",
 *     @OA\JsonContent(
 *       type="object",
 *       @OA\Property(property="message", type="string", example="Jane Doe registered"),
 *       @OA\Property(property="db_connection", type="string", example="mysql"),
 *       @OA\Property(property="db_database", type="string", example="recipes_db"),
 *       @OA\Property(property="db_host", type="string", example="127.0.0.1"),
 *       @OA\Property(property="access_token", type="string", example="1|uXb..."),
 *       @OA\Property(property="token_type", type="string", example="Bearer"),
 *       @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Jane Doe"),
 *         @OA\Property(property="email", type="string", example="jane@example.com")
 *       )
 *     )
 *   ),
 *   @OA\Response(
 *     response=422,
 *     description="Validation error",
 *     @OA\JsonContent(
 *       type="object",
 *       @OA\Property(property="message", type="string", example="Validation failed"),
 *       @OA\Property(
 *         property="errors",
 *         type="object",
 *         example={"email":{"The email has already been taken."}}
 *       )
 *     )
 *   ),
 *   @OA\Response(
 *     response=500,
 *     description="Server error"
 *   )
 * )
 */
    public function register(Request $request)
    {
        try{
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|max:255|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'message' => $user->name . ' registered',
            'db_connection' => config('database.default'),
            'db_database' => config('database.connections.' . config('database.default') . '.database'),
            'db_host' => config('database.connections.' . config('database.default') . '.host'),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);

        } catch (\Throwable $e) {
        Log::error('Register failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Server error during registration'
        ], 500);
    }
    }

    /**
 * @OA\Post(
 *   path="/api/login",
 *   tags={"Auth"},
 *   summary="Login",
 *   description="Authenticates a user and returns a Sanctum access token.",
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *       required={"email","password"},
 *       @OA\Property(property="email", type="string", format="email", example="jane@example.com"),
 *       @OA\Property(property="password", type="string", example="secret1234")
 *     )
 *   ),
 *   @OA\Response(
 *     response=200,
 *     description="Logged in",
 *     @OA\JsonContent(
 *       type="object",
 *       @OA\Property(property="message", type="string", example="Jane Doe logged in"),
 *       @OA\Property(property="access_token", type="string", example="1|uXb..."),
 *       @OA\Property(property="token_type", type="string", example="Bearer"),
 *       @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Jane Doe"),
 *         @OA\Property(property="email", type="string", example="jane@example.com"),
 *         @OA\Property(property="role", type="string", example="user")
 *       )
 *     )
 *   ),
 *   @OA\Response(
 *     response=401,
 *     description="Invalid credentials",
 *     @OA\JsonContent(type="object", example={"message":"WRONG INPUT"})
 *   )
 * )
 */
    public function login(Request $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'WRONG INPUT'], 401);
        }

        $user = User::where('email', $request['email'])->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => $user->name . ' logged in',
            'access_token' => $token,
            'user'=> $user,
            'token_type' => 'Bearer'
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/logout",
     *   tags={"Auth"},
     *   summary="Logout",
     *   description="Revokes all active tokens for the authenticated user.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="Logged out",
     *     @OA\JsonContent(type="object", example={"message":"You have successfully logged out."})
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated"
     *   )
     * )
     */
    public function logout()
    {
        auth()->user()->tokens()->delete();

        return [
            'message' => 'You have successfully logged out.'
        ];
    }
}
