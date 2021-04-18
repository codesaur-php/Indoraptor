<?php

namespace Indoraptor\Account;

use codesaur\Router\Router;

class AccountRouter extends Router
{
    function __construct()
    {
        // Authorization rules
        $this->post('/auth/jwt', [AuthController::class, 'jwt']);
        $this->post('/auth/try', [AuthController::class, 'entry']);
        $this->post('/auth/jwt/org', [AuthController::class, 'jwtOrganization']);
        
        // Account rules
        $this->post('/account/signup', [AccountController::class, 'signup']);
        $this->post('/account/forgot', [AccountController::class, 'forgot']);
        $this->post('/account/get/forgot', [AccountController::class, 'getForgot']);
        $this->post('/account/set/password', [AccountController::class, 'setPassword']);
        $this->get('/account/get/organizations/names', [AccountController::class, 'getOrganizationsNames']);
    }
}
