<?php

namespace Indoraptor\Account;

use codesaur\Router\Router;

class AccountRouter extends Router
{
    function __construct()
    {
        // Authorization rules
        $this->POST('/auth/jwt', [AuthController::class, 'jwt']);
        $this->POST('/auth/entry', [AuthController::class, 'entry']);
        $this->POST('/auth/organization', [AuthController::class, 'organization']);
        
        // Account rules
        $this->POST('/account/signup', [AccountController::class, 'signup']);
        $this->POST('/account/forgot', [AccountController::class, 'forgot']);
        $this->POST('/account/password', [AccountController::class, 'password']);
        $this->GET('/account/get/menu', [AccountController::class, 'getMenu']);
    }
}
