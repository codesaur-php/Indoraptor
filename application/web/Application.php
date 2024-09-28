<?php

namespace Web;

use Raptor\PDOConnectMiddleware;
use Raptor\Exception\ErrorHandler;
use Raptor\Localization\LocalizationMiddleware;
use Raptor\Content\SettingsMiddleware;

class Application extends \codesaur\Http\Application\Application
{
    public function __construct()
    {
        parent::__construct();
        
        $this->use(new ErrorHandler());
        
        $this->use(new PDOConnectMiddleware());
        $this->use(new LocalizationMiddleware());
        $this->use(new SettingsMiddleware());

        $this->use(new Home\HomeRouter());
    }
}
