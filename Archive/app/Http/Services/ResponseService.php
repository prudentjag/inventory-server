<?php

namespace App\Http\Services;

use Illuminate\Http\Response;

class ResponseService
{
    public static function success($data = null, $message = 'Success', $code = Response::HTTP_OK)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error($message = 'Error', $code = Response::HTTP_INTERNAL_SERVER_ERROR)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $code);
    }
}