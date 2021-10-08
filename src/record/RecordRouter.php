<?php

namespace Indoraptor\Record;

use codesaur\Router\Router;

class RecordRouter extends Router
{
    function __construct()
    {
        $this->INTERNAL('/record', [RecordController::class, 'internal']);
    }
}
