<?php

namespace Indoraptor\Contents;

use codesaur\Router\Router;

class ContentsRouter extends Router
{
    public function __construct()
    {
        $this->GET_INTERNAL('/reference/{table}', [ReferenceController::class, 'index']);
        $this->POST('/reference/{table}', [ReferenceController::class, 'insert']);
        $this->PUT('/reference/{table}', [ReferenceController::class, 'update']);
        $this->DELETE('/reference/{table}', [ReferenceController::class, 'delete']);
        $this->GET_INTERNAL('/reference/records/{table}', [ReferenceController::class, 'records']);
        
        $this->GET_INTERNAL('/files/{table}', [FilesController::class, 'index']);
        $this->POST('/files/{table}', [FilesController::class, 'insert']);
        $this->INTERNAL('/files/{table}/insert', [FilesController::class, 'insert_internal']);
        $this->PUT('/files/{table}', [FilesController::class, 'update']);
        $this->INTERNAL('/files/{table}/update', [FilesController::class, 'update_internal']);
        $this->DELETE('/files/{table}', [FilesController::class, 'delete']);
        $this->GET_INTERNAL('/files/records/{table}', [FilesController::class, 'records']);
        
        $this->INTERNAL('/pages/navigation/{code}', [PagesController::class, 'navigation']);
    }
}
