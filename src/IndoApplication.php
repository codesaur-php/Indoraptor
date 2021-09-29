<?php

namespace Indoraptor;

use codesaur\Http\Application\Application;

class IndoApplication extends Application
{
    function __construct()
    {
        parent::__construct();
        
        // import account rules
        $this->use(new Account\AccountRouter());

        // import localization rules
        $this->use(new Localization\LocalizationRouter());

        // import logger rules
        $this->use(new Logger\LoggerRouter());
        
        $this->get('/', function()
        {
            echo json_encode(array('application' => __CLASS__));
        });
    }
}
