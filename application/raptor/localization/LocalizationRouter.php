<?php 

namespace Raptor\Localization;

use codesaur\Router\Router;

/**
 * Class LocalizationRouter
 * 
 * Indoraptor framework-ийн Локализацийн модульд зориулсан маршрут 
 * тодорхойлогч класс юм. 
 * 
 * Энэ нь codesaur/router компонентын Router классыг өргөтгөн,
 * хэлний тохиргоо болон текстийн орчуулгын CRUD үйлдлүүдэд шаардлагатай
 * бүх HTTP маршрутыг бүртгэнэ.
 * 
 * Маршрутууд нь dashboard хэсэгт байрлах:
 *  - LanguageController - системийн хэлүүдийг удирдах
 *  - TextController - орчуулгын текстүүдийг удирдах
 *  - LocalizationController - локализацийн ерөнхий хуудас
 * 
 * @package Raptor\Localization
 */
class LocalizationRouter extends Router
{
    /**
     * LocalizationRouter constructor.
     *
     * Энд локализацийн бүх CRUD маршрут бүртгэгдэнэ.
     * GET, POST, PUT, DELETE зэрэг HTTP дүрэм тус бүрт тохирох
     * controller action-ууд холбогдоно.
     */
    public function __construct()
    {
        /**
         * Локализацийн үндсэн Dashboard хуудас.
         * Example: GET /dashboard/localization
         */
        $this->GET('/dashboard/localization', [LocalizationController::class, 'index'])->name('localization');
        
        /**
         * Хэл нэмэх (GET + POST нийлсэн).
         * Example: GET/POST /dashboard/language
         */
        $this->GET_POST('/dashboard/language', [LanguageController::class, 'insert'])->name('language-insert');

        /**
         * Нэг хэлний мэдээллийг харах.
         * Example: GET /dashboard/language/view/3
         */
        $this->GET('/dashboard/language/view/{uint:id}', [LanguageController::class, 'view'])->name('language-view');

        /**
         * Хэл шинэчлэх PUT хүсэлт.
         * Example: GET/PUT /dashboard/language/3
         */
        $this->GET_PUT('/dashboard/language/{uint:id}', [LanguageController::class, 'update'])->name('language-update');

        /**
         * Хэл идэвхгүй болгох (soft delete).
         * Example: DELETE /dashboard/language/deactivate
         */
        $this->DELETE('/dashboard/language/deactivate', [LanguageController::class, 'deactivate'])->name('language-deactivate');
        
        /**
         * Орчуулгын текст шинээр нэмэх (динамик хүснэгт рүү).
         * Example: GET/POST /dashboard/text/user
         */
        $this->GET_POST('/dashboard/text/{table}', [TextController::class, 'insert'])->name('text-insert');

        /**
         * Орчуулгын текст шинэчлэх.
         * Example: GET/PUT /dashboard/text/user/12
         */
        $this->GET_PUT('/dashboard/text/{table}/{uint:id}', [TextController::class, 'update'])->name('text-update');

        /**
         * Орчуулгын текстийн дэлгэрэнгүй харах.
         * Example: GET /dashboard/text/view/user/12
         */
        $this->GET('/dashboard/text/view/{table}/{uint:id}', [TextController::class, 'view'])->name('text-view');

        /**
         * Орчуулгын текст идэвхгүй болгох (зөөлөн устгал).
         * Example: DELETE /dashboard/text/deactivate
         */
        $this->DELETE('/dashboard/text/deactivate', [TextController::class, 'deactivate'])->name('text-deactivate');
    }
}
