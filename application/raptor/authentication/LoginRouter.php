<?php

namespace Raptor\Authentication;

use codesaur\Router\Router;

/**
 * Class LoginRouter
 *
 * Dashboard хэсгийн нэвтрэх үйлдлүүдийн бүх маршрутыг (routes)
 * тодорхойлдог router класс. Энэ нь хэрэглэгчийн нэвтрэх,
 * гарах, нууц үг сэргээх, бүртгүүлэх, хэл солих зэрэг 
 * authentication-тэй холбоотой бүх URL чиглүүлэлтийг aжуулна.
 *
 * Raptor-ийн Router анги нь:
 *   - GET, POST замууд үүсгэх
 *   - Dynamic параметр дэмжих {code}, {uint:id}
 *   - Маршрут бүрт нэр өгөх (name)
 * боломжуудыг олгодог.
 *
 * @package Raptor\Authentication
 */
class LoginRouter extends Router
{
    /**
     * LoginRouter constructor.
     *
     * Энд authentication-тэй холбоотой маршрут бүрийг тодорхойлно.
     * Бүх зам “/dashboard/login…” хэлбэртэй бөгөөд LoginController-ийн
     * харгалзах action-уудтай шууд холбогдоно.
     */
    public function __construct()
    {
        /**
         * ---------------------------------------------------------------
         * 1. Login хуудас (GET)
         * ---------------------------------------------------------------
         * Хэрэглэгч нэвтрэх нүүр хуудас руу орно.
         */
        $this->GET('/dashboard/login', [LoginController::class, 'index'])->name('login');

        /**
         * ---------------------------------------------------------------
         * 2. Нэвтрэх оролдлого (POST)
         * ---------------------------------------------------------------
         * Хэрэглэгч username/password илгээж нэвтрэхийг оролдоно.
         */
        $this->POST('/dashboard/login/try', [LoginController::class, 'entry'])->name('entry');

        /**
         * ---------------------------------------------------------------
         * 3. Гарах (GET)
         * ---------------------------------------------------------------
         * Session болон JWT-г цэвэрлээд хэрэглэгчийг гарах.
         */
        $this->GET('/dashboard/login/logout', [LoginController::class, 'logout'])->name('logout');

        /**
         * ---------------------------------------------------------------
         * 4. Нууц үг сэргээх (POST)
         * ---------------------------------------------------------------
         * Хэрэглэгч email/username оруулж “Forgot password” хүсэлт үүсгэнэ.
         */
        $this->POST('/dashboard/login/forgot', [LoginController::class, 'forgot'])->name('login-forgot');

        /**
         * ---------------------------------------------------------------
         * 5. Бүртгүүлэх (POST)
         * ---------------------------------------------------------------
         * Шинэ хэрэглэгч нэр, имэйл, нууц үгийн мэдээлэл өгч signup хийх.
         */
        $this->POST('/dashboard/login/signup', [LoginController::class, 'signup'])->name('signup');

        /**
         * ---------------------------------------------------------------
         * 6. Хэл солих (GET)
         * ---------------------------------------------------------------
         * Хэрэглэгч login хуудасны интерфейсийн хэлийг солих.
         * Dynamic parameter: {code}
         * Жишээ: GET /dashboard/login/language/mn
         */
        $this->GET('/dashboard/login/language/{code}', [LoginController::class, 'language'])->name('language');

        /**
         * ---------------------------------------------------------------
         * 7. Сэргээх линк дээр дараад шинэ нууц үг тохируулах (POST)
         * ---------------------------------------------------------------
         */
        $this->POST('/dashboard/login/set/password', [LoginController::class, 'setPassword'])->name('login-set-password');

        /**
         * ---------------------------------------------------------------
         * 8. Байгууллага сонгох (GET)
         * ---------------------------------------------------------------
         * Хэрэглэгч хэд хэдэн байгууллагад хамааралтай бол
         * нэвтэрсэн үедээ аль байгууллагаар ажиллахаа сонгох алхам.
         *
         * Dynamic parameter:
         *    {uint:id} → зөвхөн unsigned integer
         *
         * Жишээ: GET /dashboard/login/organization/12
         */
        $this->GET('/dashboard/login/organization/{uint:id}', [LoginController::class, 'selectOrganization'])->name('login-select-organization');
    }
}
