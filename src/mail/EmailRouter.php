<?php

namespace Indoraptor\Mail;

use codesaur\Router\Router;

class EmailRouter extends Router
{
    function __construct()
    {
        $this->INTERNAL('/send/email', [EmailController::class, 'send']);
        $this->INTERNAL('/send/stmp/email', [EmailController::class, 'sendSMTP']);
    }
}
