<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

/*
| Fortify em modo headless (sem views): a SPA chama os endpoints em /api/v1/auth.
| Os limitadores "login" e "auth" são definidos no IdentityServiceProvider.
*/
return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/',
    'prefix' => 'api/v1/auth',
    'domain' => null,
    'middleware' => ['web', 'throttle:auth'],
    'limiters' => [
        'login' => 'login',
        'verification' => '6,1',
    ],
    'views' => false,
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
    ],
];
