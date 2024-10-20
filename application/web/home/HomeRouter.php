<?php

namespace Web\Home;

use codesaur\Router\Router;

class HomeRouter extends Router
{
    public function __construct()
    {
        $this->GET('/', [HomeController::class, 'index'])->name('home');
        $this->GET('/home', [HomeController::class, 'index']);
        $this->GET('/language/{code}', [HomeController::class, 'language'])->name('language');
        
        $this->GET('/page/{uint:id}', [HomeController::class, 'page'])->name('page');
        $this->GET('/news/{uint:id}', [HomeController::class, 'news'])->name('news');
        $this->GET('/contact', [HomeController::class, 'contact'])->name('contact');
    }
}
