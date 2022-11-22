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
        $this->GET('/text', [TextController::class, 'index']);        
        $this->POST('/text/retrieve', [TextController::class, 'retrieve']);
        $this->POST('/text/find/keyword', [TextController::class, 'findKeyword']);
        $this->GET('/text/initial/methods', [TextController::class, 'getInitialMethods']);

        // Countries rules
        $this->GET('/countries', [CountriesController::class, 'index']);        
    }
}
