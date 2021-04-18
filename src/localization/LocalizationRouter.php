<?php

namespace Indoraptor\Localization;

use codesaur\Router\Router;

class LocalizationRouter extends Router
{
    function __construct()
    {
        // Language rules
        $this->get('/language', [LanguageController::class]);
        $this->post('/language/copy/multimodel/content', [LanguageController::class, 'copyMultiModelContent']);
        
        // Translation rules
        $this->get('/translation', [TranslationController::class]);        
        $this->post('/translation/retrieve', [TranslationController::class, 'retrieve']);
        $this->post('/translation/find/keyword', [TranslationController::class, 'findKeyword']);
        $this->get('/translation/initial/methods', [TranslationController::class, 'getInitialMethods']);

        // Countries rules
        $this->get('/countries', [CountriesController::class]);        
    }
}
