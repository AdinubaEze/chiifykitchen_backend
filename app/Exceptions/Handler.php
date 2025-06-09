<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable; 
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof TokenExpiredException) {
            return response()->json([
                'message' => 'Token expired',
                'status' => 'fail',
                'error' => 'token_expired'
            ], 401);
        }
    
        if ($exception instanceof TokenInvalidException) {
            return response()->json([
                'message' => 'Token invalid',
                'status' => 'fail',
                'error' => 'token_invalid'
            ], 401);
        }
    
        if ($exception instanceof JWTException) {
            return response()->json([
                'message' => 'Authorization token not provided',
                'status' => 'fail',
                'error' => 'token_absent'
            ], 401);
        }
    
        return parent::render($request, $exception);
    }
}
