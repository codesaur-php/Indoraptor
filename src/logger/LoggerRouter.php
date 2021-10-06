<?php

namespace Indoraptor\Logger;

use codesaur\Router\Router;

class LoggerRouter extends Router
{
    function __construct()
    {
        $this->GET('/log/{table}', [LoggerController::class, 'index']);
        $this->GET('/log/{table}/{int:id}', [LoggerController::class, 'index']);
        $this->POST('/log/{table}', [LoggerController::class, 'insert']);
        $this->GET('/log/get/names', [LoggerController::class, 'names']);
        $this->POST('/log/{table}/select', [LoggerController::class, 'select']);
    }
}
