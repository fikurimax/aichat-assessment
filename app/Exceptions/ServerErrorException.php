<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ServerErrorException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Server is quite busy lately, please try again!',
            'data' => null
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
