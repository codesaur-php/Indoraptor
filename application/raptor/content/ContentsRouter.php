<?php

namespace Raptor\Content;

use codesaur\Router\Router;

class ContentsRouter extends Router
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
        
        $this->GET('/dashboard/news', [NewsController::class, 'index'])->name('news');
        $this->GET('/dashboard/news/list', [NewsController::class, 'list'])->name('news-list');
        $this->GET_POST('/dashboard/news/insert', [NewsController::class, 'insert'])->name('news-insert');
        $this->GET_PUT('/dashboard/news/{uint:id}', [NewsController::class, 'update'])->name('news-update');
        $this->GET('/dashboard/news/read/{uint:id}', [NewsController::class, 'read'])->name('news-read');
        $this->GET('/dashboard/news/view/{uint:id}', [NewsController::class, 'view'])->name('news-view');
        $this->DELETE('/dashboard/news', [NewsController::class, 'delete'])->name('news-delete');
        
        $this->GET('/dashboard/pages', [PagesController::class, 'index'])->name('pages');
        $this->GET('/dashboard/pages/list', [PagesController::class, 'list'])->name('pages-list');
        $this->GET_POST('/dashboard/pages/insert', [PagesController::class, 'insert'])->name('page-insert');
        $this->GET_PUT('/dashboard/pages/{uint:id}', [PagesController::class, 'update'])->name('page-update');
        $this->GET('/dashboard/pages/read/{uint:id}', [PagesController::class, 'read'])->name('page-read');
        $this->GET('/dashboard/pages/view/{uint:id}', [PagesController::class, 'view'])->name('page-view');
        $this->DELETE('/dashboard/pages', [PagesController::class, 'delete'])->name('page-delete');
        
        $this->GET('/dashboard/references', [ReferencesController::class, 'index'])->name('references');
        $this->GET_POST('/dashboard/references/{table}', [ReferencesController::class, 'insert'])->name('reference-insert');
        $this->GET_PUT('/dashboard/references/{table}/{uint:id}', [ReferencesController::class, 'update'])->name('reference-update');
        $this->GET('/dashboard/references/view/{table}/{uint:id}', [ReferencesController::class, 'view'])->name('reference-view');
        $this->DELETE('/dashboard/references/delete', [ReferencesController::class, 'delete'])->name('reference-delete');

        $this->GET('/dashboard/settings', [SettingsController::class, 'index'])->name('settings');
        $this->POST('/dashboard/settings', [SettingsController::class, 'post']);
        $this->POST('/dashboard/settings/files', [SettingsController::class, 'files'])->name('settings-files');
    }
}
