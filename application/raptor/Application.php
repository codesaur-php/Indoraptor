<?php

namespace Raptor;

/**
 * Class Application
 *
 * Raptor (Indoraptor Dashboard) хэсгийн үндсэн Application bootstrap.
 *
 * Энэ анги нь codesaur\Http\Application\Application ангийг өргөтгөж,
 * Dashboard/Admin системийн бүх middleware болон router-үүдийг
 * тодорхой дарааллын дагуу бүртгэнэ.
 *
 * Middleware pipeline нь дараах дарааллаар ажиллана:
 *
 *   1) ErrorHandler    - Алдаа барих ба JSON/HTML error-г зохицуулна
 *   2) MySQLConnectMiddleware / PostgresConnectMiddleware
 *                      → Controller болон бусад middleware-д PDO холболт inject хийнэ
 *   3) SessionMiddleware
 *                      → PHP session эхлүүлж хэрэглэгчийн session-г удирдана
 *   4) JWTAuthMiddleware
 *                      → Session доторх JWT-г шалгаж authenticated User объект үүсгэнэ
 *   5) ContainerMiddleware
 *                      → Dependency Injection Container-г request attributes-д inject хийнэ
 *                      → PDO болон User ID-г container-д бүртгэнэ
 *   6) LocalizationMiddleware
 *                      → Хэлний жагсаалт, сонгогдсон хэл, орчуулгуудыг request attributes-д inject хийнэ
 *   7) Content\SettingsMiddleware
 *                      → Системийн тохиргоог (settings) дуудлага бүрт inject хийнэ
 *
 * Мөн дараах router-үүдийг бүртгэж өгнө:
 *
 *   - LoginRouter          → Нэвтрэх, гарах, signup, forgot-pw
 *   - UsersRouter          → Хэрэглэгчийн CRUD
 *   - OrganizationRouter   → Байгууллага + хэрэглэгчийн холбоос
 *   - RBACRouter           → Permission / Role / RBAC удирдлага
 *   - LocalizationRouter   → Хэл болон орчуулга
 *   - ContentsRouter       → File, News, Page, Reference, Settings модулиуд
 *   - LogsRouter           → Системийн логийн индекс, харах
 *   - TemplateRouter       → Dashboard UI-ийн template харгалзах маршрут
 *
 * Энэхүү Application нь Dashboard талын бүх маршрут + middleware-г
 * нэг дор авч, Indoraptor-ийн бүрэн backend pipeline-г босгодог.
 *
 * @package Raptor
 */
abstract class Application extends \codesaur\Http\Application\Application
{
    /**
     * Application constructor.
     *
     * Dashboard-ын middleware болон router-үүдийг бүртгэнэ.
     * Регистрлэгдсэн дараалал нь маш чухал -> authentication, localization,
     * settings, routing гэх мэт бүх давхаргууд pipeline бүтээнэ.
     */
    public function __construct()
    {
        parent::__construct();

        // 1. Universal error handler
        $this->use(new Exception\ErrorHandler());

        // 2. Database middleware (MySQL эсвэл Postgres сонгох боломжтой)
        $this->use(new MySQLConnectMiddleware()); 
        // $this->use(new PostgresConnectMiddleware()); // Ашиглах бол MySQL-ийн оронд

        // 3. Session ба JWT authentication pipeline
        $this->use(new Authentication\SessionMiddleware());
        $this->use(new Authentication\JWTAuthMiddleware());

        // 4. Container middleware
        $this->use(new ContainerMiddleware());

        // 5. Localization болон системийн тохиргоо
        $this->use(new Localization\LocalizationMiddleware());
        $this->use(new Content\SettingsMiddleware());

        // 5. Route mapping - Dashboard/Admin модулиудын бүртгэл
        $this->use(new Authentication\LoginRouter());
        $this->use(new User\UsersRouter());
        $this->use(new Organization\OrganizationRouter());
        $this->use(new RBAC\RBACRouter());
        $this->use(new Localization\LocalizationRouter());
        $this->use(new Content\ContentsRouter());
        $this->use(new Log\LogsRouter());
        $this->use(new Template\TemplateRouter());
    }
}
