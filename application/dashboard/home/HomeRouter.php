<?php

namespace Dashboard\Home;

use codesaur\Router\Router;

/**
 * Class HomeRouter
 * ----------------------------------------------------------------------
 * Dashboard → Home module-ийн HTTP маршрут (routing) тодорхойлогч класс.
 *
 * Энэ роутер нь Dashboard-ийн үндсэн нүүр хуудас (index) маршруттай.
 * Indoraptor Framework-ийн стандарт Router-ийг өргөтгөдөг.
 *
 * Үүрэг:
 *   - "/dashboard" хаягийг HomeController@index методтой холбох
 *   - name('home') → систем дотор "home" нэрээр маршрут дуудах боломжтой
 *
 * Ашиглах ерөнхий зарчим:
 *   - Шинэ маршрут нэмэх бол constructor дотор:
 *        $this->GET(...);
 *        $this->POST(...);
 *        $this->PUT(...);
 *        гэх мэтээр үргэлжлүүлэн тодорхойлно.
 *
 * @package Dashboard\Home
 */
class HomeRouter extends Router
{
    /**
     * HomeRouter constructor.
     * ------------------------------------------------------------------
     * Dashboard-ийн үндсэн нэг маршрут: "/dashboard"
     *
     * GET /dashboard
     *   → HomeController::index()
     *   → Dashboard-ийн нүүр цэс/статистик/overview-ийг харуулна.
     *
     * @see HomeController::index()
     */
    public function __construct()
    {
        // Dashboard main page
        $this->GET('/dashboard', [HomeController::class, 'index'])->name('home');
    }
}
