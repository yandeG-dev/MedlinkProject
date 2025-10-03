<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',

        api: __DIR__.'/../routes/api.php', 

        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Configuration pour les APIs - pas de redirection
        $middleware->redirectGuestsTo(function (Request $request) {
            // Pour les routes API, retourner null (pas de redirection)
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }
            
            // Pour les routes web, rediriger vers la page de login
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Gestion des erreurs d'authentification pour les APIs
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Non authentifié.',
                    'error' => 'Unauthenticated'
                ], 401);
            }
        });
    })->create();