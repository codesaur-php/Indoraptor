<?php

namespace Indoraptor\Logger;

use codesaur\Router\Router;

class LoggerRouter extends Router
{
    function __construct()
    {
        $this->get('/log/{table}', [LoggerController::class]);
        $this->get('/log/{table}/{int:id}', [LoggerController::class]);
        $this->post('/log/{table}', [LoggerController::class, 'insert']);
        $this->get('/log/get/names', [LoggerController::class, 'names']);
        $this->post('/log/{table}/select', [LoggerController::class, 'select']);
    }
}
