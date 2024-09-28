<?php

namespace Web\Home;

use codesaur\Router\Router;

class HomeRouter extends Router
{
    public function __construct()
    {
        $this->GET('/', [HomeController::class, 'index'])->name('home');
    }
}
