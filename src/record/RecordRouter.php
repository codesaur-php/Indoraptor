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

        $this->INDO('/record', [RecordController::class, 'internal']);
        $this->INDO('/record/rows', [RecordController::class, 'internal_rows']);
        $this->INDO('/record/insert', [RecordController::class, 'internal_insert']);
        $this->INDO('/record/update', [RecordController::class, 'internal_update']);
        
        $this->INDO('/lookup', [RecordController::class, 'lookup']);

        $this->INDO('/statement', [RecordController::class, 'statement']);
    }
}
