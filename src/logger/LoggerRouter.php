<?php

namespace Indoraptor\Logger;

use codesaur\Router\Router;

class LoggerRouter extends Router
{
    function __construct()
    {
        $this->GET('/log', [LoggerController::class, 'index']);
        $this->POST('/log', [LoggerController::class, 'insert']);
        $this->POST('/log/select', [LoggerController::class, 'select']);
        $this->GET('/log/get/names', [LoggerController::class, 'names']);
    }
}
