<?php

namespace Indoraptor\Contents;

use codesaur\Router\Router;

class ContentsRouter extends Router
{
    function __construct()
    {
        // ContentModel rules
        $this->local('/content', [ContentController::class]);
    }
}
