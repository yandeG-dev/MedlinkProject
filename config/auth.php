<?php

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'sanctum', // Utilisez 'sanctum' pour l'API
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    // AJOUTEZ CETTE SECTION POUR LES REDIRECTIONS
    'redirects' => [
        'login' => 'api.auth.login',    // Nom de votre route API login
        'logout' => 'api.auth.logout',  // Nom de votre route API logout
        'home' => '/',                  // Après connexion réussie
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];