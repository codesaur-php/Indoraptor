<?php

namespace Indoraptor;

use codesaur\Http\Application\Application;

class IndoApplication extends Application
{
    function __construct()
    {
        parent::__construct();
        
        $this->use(new PDOConnectMiddleware());

        // import account rules
        $this->use(new Auth\AuthRouter());

        // import localization rules
        $this->use(new Localization\LocalizationRouter());

        // import logger rules
        $this->use(new Logger\LoggerRouter());
        
        // import record rules
        $this->use(new Record\RecordRouter());

        // import contents rules
        $this->use(new Contents\ContentsRouter());

        // import statement rules
        $this->use(new Statement\StatementRouter());

        // import emailing rules
        $this->use(new Mailer\MailerRouter());

        $this->GET('/', function()
        {
            echo json_encode(array('application' => __CLASS__));
        });
    }
}
