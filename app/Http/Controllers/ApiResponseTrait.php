<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    protected function apiSuccess($data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    protected function apiError(string $message = '', $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $status);
    }
}