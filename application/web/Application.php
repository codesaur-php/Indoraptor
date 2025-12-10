<?php

namespace Web;

/**
 * Class Application
 * ---------------------------------------------------------
 * 🌐 Indoraptor Framework - Веб давхаргын үндсэн Application класс.
 *
 * Энэ класс нь таны веб системийн “үндсэн эхлэл” бөгөөд
 * HTTP Layer дээр хэрэгжих бүх Middleware болон Router-ийг
 * зөв дарааллаар бүртгэж ажиллуулдаг.
 *
 * ✔ Middleware-үүдийг дарааллаар бүртгэн идэвхжүүлнэ  
 * ✔ Template хөдөлгүүрийн Exception Handler ашиглана  
 * ✔ Өгөгдлийн сангийн холболтыг автоматаар үүсгэнэ  
 * ✔ Session, Localization, Settings зэрэг системийн суурь
 *   давхаргыг идэвхжүүлнэ  
 * ✔ Эцэст нь вебийн үндсэн маршрутыг бүртгэнэ
 *
 * ---------------------------------------------------------
 * 🧩 Middleware-ийн дарааллын тайлбар
 * ---------------------------------------------------------
 * 1) **Template\ExceptionHandler**  
 *    - Template ашиглан error page рендерлэх  
 *    - Хэрвээ Template алга бол кодын default ExceptionHandler ажиллана  
 *
 * 2) **MySQLConnectMiddleware / PostgresConnectMiddleware**  
 *    - PDO instance үүсгэж, хожим нь Controller-т дамжуулна  
 *    - DB connection автоматаар нээгдэж хаагдана  
 *
 * 3) **ContainerMiddleware**  
 *    - Dependency Injection Container-г request attributes-д inject хийнэ  
 *    - PDO-г container-д бүртгэнэ  
 *
 * 4) **SessionMiddleware**  
 *    - PHP session удирдах  
 *    - Хэрэглэгчийн authentication / session-based data хадгалах  
 *
 * 5) **LocalizationMiddleware**  
 *    - Системийн хэл (mn/en/...) тодорхойлох  
 *    - Twig template-д localization объект дамжуулах  
 *
 * 6) **SettingsMiddleware**  
 *    - System settings (branding, favicon, footer, title, зэрэг)  
 *    - Хуудсуудад дамжуулах болно  
 *
 * ---------------------------------------------------------
 * 🚦 Router бүртгэх
 * ---------------------------------------------------------
 * ✔ `HomeRouter` - вэбийн үндсэн хуудсуудын маршрут  
 *    / → /home, contact, language гэх мэт  
 *
 * Хэрвээ та өөр Router нэмэх бол:
 *
 *      $this->use(new Products\ProductsRouter());
 *      $this->use(new News\NewsRouter());
 *      $this->use(new Auth\AuthRouter());
 *
 * гэх мэтээр нэмж болно.
 *
 * ---------------------------------------------------------
 * 🧑‍💻 Хөгжүүлэгчид зориулсан тэмдэглэл
 * ---------------------------------------------------------
 * ✔ Application нь Middleware-үүдийг **өргөтгөх боломжтой**  
 * ✔ Router-уудыг хүссэнээрээ бүлэглэн зохион байгуулж болно  
 * ✔ Custom exception handler бичээд Application->use() ашиглан  
 *   override хийж бүртгэж болно  
 *
 * @package Web
 */
class Application extends \codesaur\Http\Application\Application
{
    public function __construct()
    {
        parent::__construct();
        
        // 🎭 Template тулгуурласан Error Handler
        $this->use(new Template\ExceptionHandler());
        
        // 🗄️ Database connection (MySQL эсвэл Postgres)
        $this->use(new \Raptor\MySQLConnectMiddleware()); 
        // → Хэрэв PostgreSQL ашиглавал:
        // $this->use(new \Raptor\PostgresConnectMiddleware());

        // 📦 Container middleware (PDO шаардлагатай тул Database-ийн дараа)
        $this->use(new \Raptor\ContainerMiddleware());

        // 🔐 Session middleware
        $this->use(new SessionMiddleware());

        // 🌍 Localization middleware (mn/en ...)
        $this->use(new LocalizationMiddleware());

        // ⚙️ System settings middleware (branding, favicon, footer...)
        $this->use(new \Raptor\Content\SettingsMiddleware());

        // 🏠 Вебийн үндсэн маршрут
        $this->use(new Home\HomeRouter());
    }
}
