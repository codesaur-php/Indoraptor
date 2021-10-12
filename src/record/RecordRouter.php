<?php

namespace Indoraptor\Record;

use codesaur\Router\Router;

class RecordRouter extends Router
{
    function __construct()
    {
        $this->INTERNAL('/record', [RecordController::class, 'internal']);
        $this->INTERNAL('/record/rows', [RecordController::class, 'internal_rows']);
        $this->INTERNAL('/record/update', [RecordController::class, 'internal_update']);
    }
}
