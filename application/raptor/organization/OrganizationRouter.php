<?php

namespace Raptor\Organization;

use codesaur\Router\Router;

class OrganizationRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard/organizations', [OrganizationController::class, 'index'])->name('organizations');
        $this->GET('/dashboard/organizations/list', [OrganizationController::class, 'list'])->name('organizations-list');
        $this->GET_POST('/dashboard/organizations/insert', [OrganizationController::class, 'insert'])->name('organization-insert');
        $this->GET_PUT('/dashboard/organizations/update/{uint:id}', [OrganizationController::class, 'update'])->name('organization-update');
        $this->GET('/dashboard/organizations/view/{uint:id}', [OrganizationController::class, 'view'])->name('organization-view');
        $this->DELETE('/dashboard/organizations/deactivate', [OrganizationController::class, 'deactivate'])->name('organization-deactivate');

        $this->GET('/dashboard/organization/user/list', [OrganizationUserController::class, 'index'])->name('organization-user');
    }
}
