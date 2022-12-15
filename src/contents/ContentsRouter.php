<?php

namespace Indoraptor\Contents;

use codesaur\Router\Router;

class ContentsRouter extends Router
{
    function __construct()
    {
        $this->POST_INTERNAL('/reference', [ReferenceController::class, 'internal']);
    }
}
