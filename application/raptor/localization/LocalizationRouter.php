<?php

namespace Raptor\Localization;

use codesaur\Router\Router;

class LocalizationRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard/localization', [LocalizationController::class, 'index'])->name('localization');
        
        $this->GET_POST('/dashboard/language', [LanguageController::class, 'insert'])->name('language-insert');
        $this->GET('/dashboard/language/view/{uint:id}', [LanguageController::class, 'view'])->name('language-view');
        $this->GET_PUT('/dashboard/language/{uint:id}', [LanguageController::class, 'update'])->name('language-update');
        $this->DELETE('/dashboard/language', [LanguageController::class, 'delete'])->name('language-delete');
        
        $this->GET_POST('/dashboard/text/{table}', [TextController::class, 'insert'])->name('text-insert');
        $this->GET_PUT('/dashboard/text/{table}/{uint:id}', [TextController::class, 'update'])->name('text-update');
        $this->GET('/dashboard/text/view/{table}/{uint:id}', [TextController::class, 'view'])->name('text-view');
        $this->DELETE('/dashboard/text/delete', [TextController::class, 'delete'])->name('text-delete');
    }
}
