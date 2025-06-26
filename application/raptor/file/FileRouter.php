<?php

namespace Raptor\File;

use codesaur\Router\Router;

class FileRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard/files', [FilesController::class, 'index'])->name('files');
        $this->GET('/dashboard/files/list/{table}', [FilesController::class, 'list'])->name('files-list');
        $this->POST('/dashboard/files/{input}/{table}/{uint:id}', [FilesController::class, 'post'])->name('files-post');
        $this->GET('/dashboard/files/modal/{table}', [PrivateFilesController::class, 'modal'])->name('files-modal');
        $this->PUT('/dashboard/files/{table}/{uint:id}', [PrivateFilesController::class, 'update'])->name('files-update');
        $this->DELETE('/dashboard/files/{table}', [PrivateFilesController::class, 'delete'])->name('files-delete');
        $this->GET('/dashboard/private/file', [PrivateFilesController::class, 'read'])->name('private-files-read');
    }
}
