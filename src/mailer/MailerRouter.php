<?php

namespace Indoraptor\Mailer;

use codesaur\Router\Router;

class MailerRouter extends Router
{
    public function __construct()
    {
        $this->INTERNAL('/send/email', [MailerController::class, 'send']);
        $this->INTERNAL('/send/smtp/email', [MailerController::class, 'sendSMTP']);
    }
}
