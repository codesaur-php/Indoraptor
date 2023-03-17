<?php

namespace Indoraptor\File;

use codesaur\Router\Router;

class FilesRouter extends Router
{
    public function __construct()
    {
        $this->GET_INTERNAL('/files/{table}', [FilesController::class, 'index']);
        $this->POST('/files/{table}', [FilesController::class, 'insert']);
        $this->INTERNAL('/files/{table}/insert', [FilesController::class, 'internal']);
        $this->PUT('/files/{table}', [FilesController::class, 'update']);
        $this->DELETE('/files/{table}', [FilesController::class, 'delete']);
        $this->GET_INTERNAL('/files/records/{table}', [FilesController::class, 'records']);
    }
}
