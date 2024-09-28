<?php

namespace Web\Home;

use Psr\Log\LogLevel;

class HomeController extends \Raptor\Controller
{
    public function index()
    {
        echo 'Hello world!';
        
        $this->indolog('web', LogLevel::NOTICE, 'Нүүр хуудсыг уншиж байна');
    }
}
