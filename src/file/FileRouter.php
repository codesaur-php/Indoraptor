<?php

namespace Indoraptor\File;

use codesaur\Router\Router;

class FilesRouter extends Router
{
    function __construct()
    {
        $this->GET_INTERNAL('/files/index/{table}', [FilesController::class, 'index']);        
        $this->GET_INTERNAL('/files/{table}', [FilesController::class, 'record']);
        $this->POST('/files/{table}', [FilesController::class, 'insert']);
        $this->PUT('/files/{table}', [FilesController::class, 'update']);
        $this->DELETE('/files/{table}', [FilesController::class, 'delete']);    
    }
}
