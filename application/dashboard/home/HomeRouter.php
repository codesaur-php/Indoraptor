<?php

namespace Dashboard\Home;

use codesaur\Router\Router;

class HomeRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard', [HomeController::class, 'index'])->name('home');
    }
}
