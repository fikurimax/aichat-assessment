<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Exception\BadRequestException as ExceptionBadRequestException;
use Symfony\Component\HttpFoundation\Response;

class BadRequestException extends ExceptionBadRequestException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->message,
            'data' => null
        ], Response::HTTP_BAD_REQUEST);
    }
}
