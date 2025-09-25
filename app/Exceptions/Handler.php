<?php

namespace App\Exceptions;


use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\JsonResponse;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
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

public function register()
{
    $this->renderable(function (AuthenticationException $e, Request $request) {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
                'error' => 'Token invalide, expiré ou manquant'
            ], 401);
        }
        
        return redirect()->guest(route('login'));
    });
}

// OU la méthode traditionnelle :
protected function unauthenticated($request, AuthenticationException $exception)
{
    if ($request->expectsJson() || $request->is('api/*')) {
        return response()->json([
            'success' => false,
            'message' => 'Non authentifié',
            'error' => 'Token invalide, expiré ou manquant'
        ], 401);
    }
    
    return redirect()->guest(route('login'));
}

}