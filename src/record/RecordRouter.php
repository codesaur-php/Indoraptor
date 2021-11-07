<?php

namespace Indoraptor\Record;

use codesaur\Router\Router;

class RecordRouter extends Router
{
    function __construct()
    {
        $this->POST('/record', [RecordController::class, 'insert']);
        $this->PUT('/record', [RecordController::class, 'update']);
        $this->DELETE('/record', [RecordController::class, 'delete']);

        $this->INTERNAL('/record', [RecordController::class, 'internal']);
        $this->INTERNAL('/record/rows', [RecordController::class, 'internal_rows']);
        $this->INTERNAL('/record/insert', [RecordController::class, 'internal_insert']);
        $this->INTERNAL('/record/update', [RecordController::class, 'internal_update']);
        
        $this->INTERNAL('/lookup', [RecordController::class, 'lookup']);

        $this->INTERNAL('/statement', [RecordController::class, 'statement']);
    }
}
