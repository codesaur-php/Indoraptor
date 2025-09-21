<?php

namespace Raptor\Template;

use codesaur\Router\Router;

class TemplateRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard/user/option', [TemplateController::class, 'userOption'])->name('user-option');
        
        $this->GET('/dashboard/manage/menu', [TemplateController::class, 'manageMenu'])->name('manage-menu');
        $this->POST('/dashboard/manage/menu/insert', [TemplateController::class, 'manageMenuInsert'])->name('manage-menu-insert');
        $this->PUT('/dashboard/manage/menu/update', [TemplateController::class, 'manageMenuUpdate'])->name('manage-menu-update');
        $this->DELETE('/dashboard/manage/menu/deactivate', [TemplateController::class, 'manageMenuDeactivate'])->name('manage-menu-deactivate');
    }
}
