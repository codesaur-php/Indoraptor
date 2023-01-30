<?php

namespace Indoraptor\Record;

use codesaur\Router\Router;

class RecordRouter extends Router
{
    public function __construct()
    {
        $this->GET('/record', [RecordController::class, 'record']);
        $this->GET('/records', [RecordController::class, 'records']);
        $this->POST('/record', [RecordController::class, 'insert']);
        $this->PUT('/record', [RecordController::class, 'update']);
        $this->DELETE('/record', [RecordController::class, 'delete']);
        
        $this->INTERNAL('/record', [RecordController::class, 'internal_record']);
        $this->INTERNAL('/records', [RecordController::class, 'internal_records']);
        $this->INTERNAL('/record/insert', [RecordController::class, 'internal_insert']);
        $this->INTERNAL('/record/update', [RecordController::class, 'internal_update']);
        $this->INTERNAL('/record/delete', [RecordController::class, 'internal_delete']);        
    }
}
