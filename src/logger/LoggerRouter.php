<?php

namespace Indoraptor\Logger;

use codesaur\Router\Router;

class LoggerRouter extends Router
{
    public function __construct()
    {
        $this->GET('/log', [LoggerController::class, 'index']);
        $this->POST('/log', [LoggerController::class, 'insert']);
        $this->INTERNAL('/log', [LoggerController::class, 'internal']);
        $this->POST('/log/select', [LoggerController::class, 'select']);
        $this->GET('/log/get/names', [LoggerController::class, 'names']);
    }
}
