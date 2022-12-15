<?php

namespace Indoraptor\Localization;

use codesaur\Router\Router;

class LocalizationRouter extends Router
{
    function __construct()
    {
        // Language rules
        $this->GET('/language', [LanguageController::class, 'index']);
        $this->POST('/language/copy/multimodel/content', [LanguageController::class, 'copyMultiModelContent']);
        
        // Text rules
        $this->INTERNAL('/text/table/create', [TextController::class, 'create']);
        $this->GET('/text/table/names', [TextController::class, 'names']);
        $this->POST('/text/retrieve', [TextController::class, 'retrieve']);
        $this->POST('/text/find/keyword', [TextController::class, 'findKeyword']);
        
        // Countries rules
        $this->GET('/countries', [CountriesController::class, 'index']);        
    }
}
