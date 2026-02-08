<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="API",
 *     version="1.0.0",
 *     description="API documentation for Recipes Web Shop."
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Development server"
 * )
 */
class ApiDoc extends Controller {}