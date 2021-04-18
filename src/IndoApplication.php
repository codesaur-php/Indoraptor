<?php

namespace Indoraptor;

use codesaur\Http\Application\Application;

class IndoApplication extends Application
{
    function __construct()
    {
        parent::__construct();
        
        $this->setPipe('/indo');

        // import account rules
        $this->merge(new Account\AccountRouter());

        // import localization rules
        $this->merge(new Localization\LocalizationRouter());

        // import logger rules
        $this->merge(new Logger\LoggerRouter());
        
        $this->get('/', function()
        {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
                        
            echo json_encode(array('application' => __CLASS__));
        });
    }
}
