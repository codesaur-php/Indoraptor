<?php

namespace Dashboard;

class Application extends \Raptor\Application
{
    public function __construct()
    {
        parent::__construct();
        
        $this->use(new Home\HomeRouter());
    }
}
