<?php

namespace Web;

class Application extends \codesaur\Http\Application\Application
{
    public function __construct()
    {
        parent::__construct();
        
        $this->use(new Template\ExceptionHandler());
        
        $this->use(new \Raptor\PostgresConnectMiddleware());
        $this->use(new SessionMiddleware());
        $this->use(new LocalizationMiddleware());
        $this->use(new \Raptor\Content\SettingsMiddleware());

        $this->use(new Home\HomeRouter());
    }
}
