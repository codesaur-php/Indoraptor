<?php

namespace Raptor\User;

use codesaur\Router\Router;

class UsersRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard/users', [UsersController::class, 'index'])->name('users');
        $this->GET('/dashboard/users/list', [UsersController::class, 'list'])->name('users-list');
        $this->GET_POST('/dashboard/users/insert', [UsersController::class, 'insert'])->name('user-insert');
        $this->GET_PUT('/dashboard/users/update/{uint:id}', [UsersController::class, 'update'])->name('user-update');
        $this->GET('/dashboard/users/view/{uint:id}', [UsersController::class, 'view'])->name('user-view');
        $this->DELETE('/dashboard/users/delete', [UsersController::class, 'delete'])->name('user-delete');
        $this->GET_POST('/dashboard/users/set/user/role', [UsersController::class, 'setUserRole'])->name('users-set-role');
        $this->GET_POST('/dashboard/users/set/organization', [UsersController::class, 'setOrganization'])->name('users-set-organization');
        
        $this->GET('/dashboard/users/requests/{table}/modal', [UsersController::class, 'requestsModal'])->name('user-requests-modal');
        $this->POST('/dashboard/users/request/approve', [UsersController::class, 'requestApprove'])->name('user-request-approve');
        $this->DELETE('/dashboard/users/request/delete', [UsersController::class, 'requestDelete'])->name('user-request-delete');
    }
}
