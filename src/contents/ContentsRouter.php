<?php

namespace Indoraptor\Contents;

use codesaur\Router\Router;

class ContentsRouter extends Router
{
    function __construct()
    {
        // ContentModel rules
        $this->INTERNAL('/content', [ContentController::class, 'index']);
    }
}
