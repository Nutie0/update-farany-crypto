<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponseFormatter
{
    public function formatResponse($data, string $status = 'success', int $code = 200): JsonResponse
    {
        return new JsonResponse([
            'status' => $status,
            'data' => $data
        ], $code);
    }

    public function formatError(string $message, int $code = 400): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => $message
        ], $code);
    }
}
