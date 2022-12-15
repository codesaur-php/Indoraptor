<?php

namespace Indoraptor\Record;

use codesaur\Router\Router;

class RecordRouter extends Router
{
    function __construct()
    {
        $this->POST_INTERNAL('/record', [RecordController::class, 'index']);        
        $this->POST_INTERNAL('/record/rows', [RecordController::class, 'rows']);        
        $this->POST_INTERNAL('/record/insert', [RecordController::class, 'insert']);        
        $this->PUT_INTERNAL('/record/update', [RecordController::class, 'update']);        
        $this->DELETE_INTERNAL('/record/delete', [RecordController::class, 'delete']);
    }
}
