<?php

namespace Raptor\Template;

use codesaur\Router\Router;

/**
 * Class TemplateRouter
 *
 * Indoraptor Framework-ийн Template модулийн Dashboard-той холбоотой
 * маршрут (route)-уудыг нэг дор бүртгэн удирддаг Router.
 *
 * Гүйцэтгэх үндсэн үүргүүд:
 *   - Хэрэглэгчийн UI тохиргоонуудыг (хэл, харагдах байдал гэх мэт) авах
 *   - Dashboard менюг удирдах (үүсгэх, засах, идэвхгүй болгох)
 *
 * Энэ Router нь TemplateController-т холбогддог.
 *
 * @package Raptor\Template
 */
class TemplateRouter extends Router
{
    /**
     * TemplateRouter constructor.
     *
     * Энд Dashboard UI-тай холбоотой бүх замуудыг бүртгэнэ.
     * 
     * Жишээ:
     *   GET    /dashboard/user/option             → Хэрэглэгчийн DASHBOARD UI тохиргоо
     *   GET    /dashboard/manage/menu             → Меню удирдлага хуудас
     *   POST   /dashboard/manage/menu/insert      → Шинэ меню үүсгэх
     *   PUT    /dashboard/manage/menu/update      → Одоогийн менюг шинэчлэх
     *   DELETE /dashboard/manage/menu/deactivate  → Менюг идэвхгүй болгох
     */
    public function __construct()
    {
        /**
         * ХЭРЭГЛЭГЧИЙН DASHBOARD UI ТОХИРГОО
         */
        $this->GET(
            '/dashboard/user/option',
            [TemplateController::class, 'userOption']
        )->name('user-option');

        /**
         * МЕНЮ УДИРДЛАГА (Dashboard → System → Menu Management)
         */

        // Меню жагсаалт, удирдлагын хуудас
        $this->GET(
            '/dashboard/manage/menu',
            [TemplateController::class, 'manageMenu']
        )->name('manage-menu');

        // Шинэ меню үүсгэх
        $this->POST(
            '/dashboard/manage/menu/insert',
            [TemplateController::class, 'manageMenuInsert']
        )->name('manage-menu-insert');

        // Меню шинэчлэх
        $this->PUT(
            '/dashboard/manage/menu/update',
            [TemplateController::class, 'manageMenuUpdate']
        )->name('manage-menu-update');

        // Меню идэвхгүй болгох
        $this->DELETE(
            '/dashboard/manage/menu/deactivate',
            [TemplateController::class, 'manageMenuDeactivate']
        )->name('manage-menu-deactivate');

        /**
         * MOEDIT SHINE API
         *
         * moedit editor-ийн "shine" товчинд зориулсан API.
         * HTML контентыг OpenAI ашиглан Bootstrap 5 компонентуудтай болгоно.
         */
        $this->POST(
            '/dashboard/ai/shine',
            [TemplateController::class, 'AIShine']
        )->name('moedit-ai-shine');

        /**
         * MOEDIT PDF PARSE API
         *
         * moedit editor-ийн "pdf" товчинд зориулсан API.
         * PDF файлыг HTML болгон хөрвүүлнэ.
         */
        $this->POST(
            '/dashboard/moedit/pdf-parse',
            [TemplateController::class, 'PDFParse']
        )->name('moedit-pdf-parse');
    }
}
