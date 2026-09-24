<?php

namespace App\Http;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Standard success response.
     */
    public static function success(mixed $data = null, string $message = 'Request successful', int $statusCode = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Standard error response.
     */
    public static function error(string $message = 'Something went wrong', mixed $errors = null, int $statusCode = 400): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $statusCode);
    }
}
