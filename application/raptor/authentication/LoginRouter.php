<?php

namespace Raptor\Authentication;

use codesaur\Router\Router;

class LoginRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard/login', [LoginController::class, 'index'])->name('login');
        $this->POST('/dashboard/login/try', [LoginController::class, 'entry'])->name('entry');
        $this->GET('/dashboard/login/logout', [LoginController::class, 'logout'])->name('logout');
        $this->POST('/dashboard/login/forgot', [LoginController::class, 'forgot'])->name('login-forgot');
        
        $this->POST('/dashboard/login/signup', [LoginController::class, 'signup'])->name('signup');
        $this->GET('/dashboard/login/language/{code}', [LoginController::class, 'language'])->name('language');
        $this->POST('/dashboard/login/set/password', [LoginController::class, 'setPassword'])->name('login-set-password');
        $this->GET('/dashboard/login/organization/{uint:id}', [LoginController::class, 'selectOrganization'])->name('login-select-organization');
    }
}
