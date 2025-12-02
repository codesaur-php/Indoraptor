<?php

namespace Dashboard;

/**
 * Class Application
 * --------------------------------------------------------------------
 * Dashboard модулийн үндсэн Application класс.
 *
 * Энэ класс нь Raptor\Application-ийг өргөтгөн,
 * тухайн системийн (эсвэл тухайн модулийн) роутеруудыг бүртгэх,
 * middleware болон component-уудыг залгах үндсэн зориулалттай.
 *
 * Ашиглалт:
 *  - Dashboard бүхий бүх HTTP маршрут (routes) эндээс эхэлнэ.
 *  - Шаардлагатай Router, ExceptionHandler, Middleware-уудыг $this->use() ашиглан бүртгэнэ.
 *  - parent::__construct() нь Raptor\Framework-ийн гол bootstrap процессыг эхлүүлнэ.
 *
 * @package Dashboard
 */
class Application extends \Raptor\Application
{
    /**
     * Application constructor.
     * ------------------------------------------------------------------
     * Dashboard модуль ажиллаж эхлэхэд хамгийн түрүүнд ачаалагдана.
     *
     * Процесс:
     *  1) parent::__construct() → Indoraptor Framework-ийн үндсэн engine асаана
     *  2) $this->use(Home\HomeRouter::class) → Dashboard-ийн үндсэн router-ийг бүртгэнэ
     *
     * Нэмэх боломж:
     *  - Хэрэв дараа нь SettingsRouter, UserRouter гэх мэт нэмэх бол
     *    $this->use(new Settings\SettingsRouter());
     *    $this->use(new User\UserRouter());
     *    гэх мэтээр өргөтгөнө.
     */
    public function __construct()
    {
        parent::__construct();

        // Dashboard-ийн Home модулийн Router-г бүртгэж байна
        $this->use(new Home\HomeRouter());
    }
}
