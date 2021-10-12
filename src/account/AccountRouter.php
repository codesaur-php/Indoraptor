<?php

namespace Indoraptor\Account;

use codesaur\Router\Router;

class AccountRouter extends Router
{
    function __construct()
    {
        // Authorization rules
        $this->POST('/auth/jwt', [AuthController::class, 'jwt']);
        $this->POST('/auth/try', [AuthController::class, 'entry']);
        $this->POST('/auth/jwt/org', [AuthController::class, 'jwtOrganization']);
        
        // Account rules
        $this->POST('/account/signup', [AccountController::class, 'signup']);
        $this->POST('/account/forgot', [AccountController::class, 'forgot']);
        $this->POST('/account/set/password', [AccountController::class, 'setPassword']);
    }
}
