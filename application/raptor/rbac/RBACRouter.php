<?php

namespace Raptor\RBAC;

use codesaur\Router\Router;

class RBACRouter extends Router
{
    function __construct()
    {
        $this->GET('/dashboard/organizations/rbac/alias', [RBACController::class, 'alias'])->name('rbac-alias');
        $this->GET('/dashboard/organizations/rbac/role/view', [RBACController::class, 'viewRole'])->name('rbac-role-view');
        $this->GET_POST('/dashboard/organizations/rbac/{alias}/insert/role', [RBACController::class, 'insertRole'])->name('rbac-insert-role');
        $this->GET_POST('/dashboard/organizations/rbac/{alias}/insert/permission', [RBACController::class, 'insertPermission'])->name('rbac-insert-permission');
        $this->POST_DELETE('/dashboard/organizations/rbac/{alias}/role/permission', [RBACController::class, 'setRolePermission'])->name('rbac-set-role-permission');
    }
}
