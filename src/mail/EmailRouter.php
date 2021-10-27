<?php

namespace Indoraptor\Mail;

use codesaur\Router\Router;

class EmailRouter extends Router
{
    function __construct()
    {
        $this->INDO('/send/email', [EmailController::class, 'send']);
        $this->INDO('/send/stmp/email', [EmailController::class, 'sendSMTP']);
    }
}
