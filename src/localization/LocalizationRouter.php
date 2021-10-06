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
        
        // Translation rules
        $this->GET('/translation', [TranslationController::class, 'index']);        
        $this->POST('/translation/retrieve', [TranslationController::class, 'retrieve']);
        $this->POST('/translation/find/keyword', [TranslationController::class, 'findKeyword']);
        $this->GET('/translation/initial/methods', [TranslationController::class, 'getInitialMethods']);

        // Countries rules
        $this->GET('/countries', [CountriesController::class, 'index']);        
    }
}
