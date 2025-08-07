<?php

namespace Raptor\Log;

use codesaur\Router\Router;

class LogsRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard/logs', [LogsController::class, 'index'])->name('logs');
        $this->GET('/dashboard/logs/view', [LogsController::class, 'view'])->name('logs-view');
        $this->POST('/dashboard/logs/{table}/retrieve', [LogsController::class, 'retrieve'])->name('logs-retrieve');
    }
}
