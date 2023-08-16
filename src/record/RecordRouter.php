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
        
        $this->INTERNAL('/record', [RecordController::class, 'record_internal']);
        $this->INTERNAL('/records', [RecordController::class, 'records_internal']);
        $this->INTERNAL('/record/insert', [RecordController::class, 'insert_internal']);
        $this->INTERNAL('/record/update', [RecordController::class, 'update_internal']);
        $this->INTERNAL('/record/delete', [RecordController::class, 'delete_internal']);
    }
}
