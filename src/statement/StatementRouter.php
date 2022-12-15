<?php

namespace Indoraptor\Statement;

use codesaur\Router\Router;

class StatementRouter extends Router
{
    function __construct()
    {
        $this->POST_INTERNAL('/statement', [StatementController::class, 'index']);
    }
}
