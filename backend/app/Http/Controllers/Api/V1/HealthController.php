<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Return health status of the API.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'API is healthy',
        ], 200);
    }
}
