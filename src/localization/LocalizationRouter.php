<?php

namespace Indoraptor\Localization;

use codesaur\Router\Router;

class LocalizationRouter extends Router
{
    public function __construct()
    {
        // Language rules
        $this->GET('/language', [LanguageController::class, 'index']);
        $this->POST('/language/copy/multimodel/content', [LanguageController::class, 'copyMultiModelContent']);
        
        // Text rules
        $this->POST_INTERNAL('/text/table/create/{table}', [TextController::class, 'create']);
        $this->GET('/text/table/names', [TextController::class, 'names']);
        $this->POST('/text/retrieve', [TextController::class, 'retrieve']);
        $this->POST('/text/find/keyword', [TextController::class, 'findKeyword']);
        
        $this->GET_INTERNAL('/text/{table}', [TextController::class, 'record']);
        $this->POST('/text/{table}', [TextController::class, 'insert']);
        $this->PUT('/text/{table}', [TextController::class, 'update']);
        $this->DELETE('/text/{table}', [TextController::class, 'delete']);
        $this->GET_INTERNAL('/text/records/{table}', [TextController::class, 'records']);
        
        // Countries rules
        $this->GET_INTERNAL('/countries', [CountriesController::class, 'index']);
    }
}
