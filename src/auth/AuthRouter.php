<?php

namespace Indoraptor\Auth;

use codesaur\Router\Router;

class AuthRouter extends Router
{
    function __construct()
    {
        $this->POST('/auth/jwt', [AuthController::class, 'jwt']);
        $this->POST('/auth/entry', [AuthController::class, 'entry']);
        $this->POST('/auth/organization', [AuthController::class, 'organization']);
    }
}
