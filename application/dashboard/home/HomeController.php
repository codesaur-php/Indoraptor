<?php

namespace Dashboard\Home;

use Psr\Log\LogLevel;

class HomeController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        $this->twigDashboard(\dirname(__FILE__) . '/home.html')->render();
        
        $this->indolog('dashboard', LogLevel::NOTICE, 'Нүүр хуудсыг уншиж байна');
    }
}
