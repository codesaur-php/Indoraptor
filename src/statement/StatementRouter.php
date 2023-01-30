<?php

namespace Indoraptor\Statement;

use codesaur\Router\Router;

class StatementRouter extends Router
{
    public function __construct()
    {
        $this->POST_INTERNAL('/statement', [StatementController::class, 'index']);
    }
}
