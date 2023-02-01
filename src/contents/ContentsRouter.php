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
    }
}
