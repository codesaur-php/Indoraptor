<?php

namespace Indoraptor\Statement;

use codesaur\Router\Router;

class StatementRouter extends Router
{
    function __construct()
    {
        $this->POST('/reference', [StatementController::class, 'index']);
        $this->INTERNAL('/reference', [StatementController::class, 'internal']);
    }
}
