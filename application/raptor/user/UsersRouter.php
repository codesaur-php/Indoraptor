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
        $this->DELETE('/dashboard/users/deactivate', [UsersController::class, 'deactivate'])->name('user-deactivate');
        $this->GET_POST('/dashboard/users/set/password', [UsersController::class, 'setPassword'])->name('user-set-password');
        $this->GET_POST('/dashboard/users/set/role', [UsersController::class, 'setRole'])->name('user-set-role');
        $this->GET_POST('/dashboard/users/set/organization', [UsersController::class, 'setOrganization'])->name('user-set-organization');
        
        $this->GET('/dashboard/users/requests/{table}/modal', [UsersController::class, 'requestsModal'])->name('user-requests-modal');
        $this->POST('/dashboard/users/signup/approve', [UsersController::class, 'signupApprove'])->name('user-signup-approve');
        $this->DELETE('/dashboard/users/signup/deactivate', [UsersController::class, 'signupDeactivate'])->name('user-signup-deactivate');
    }
}
