<?php

namespace Indoraptor\Internal;

use codesaur\Router\Router;

class InternalRouter extends Router
{
    public function __construct()
    {
        $this->INTERNAL('/execute/fetch/all', [InternalController::class, 'executeFetchAll']);
    }
}
