<?php

namespace Web\Home;

use codesaur\Router\Router;

/**
 * Class HomeRouter
 * -------------------------------------------------------------
 * 🌐 Indoraptor Framework - Web Layer Public Router
 *
 * Энэ класс нь тухайн веб сайтын **public-facing** (зочдод харагдах)
 * үндсэн маршрутуудыг (routes) тодорхойлдог.
 *
 * ✨ Агуулга:
 * -------------------------------------------------------------
 * ✔ Нүүр хуудас (/)  
 * ✔ /home - нүүр хуудасны alias  
 * ✔ Хэл солих - /language/{code}  
 * ✔ Статик/динамик Page - /page/{slug}
 * ✔ News - /news/{slug}  
 * ✔ Холбоо барих - /contact  
 *
 * ⚡ Router-ийн онцлог:
 * -------------------------------------------------------------
 * Indoraptor-ийн Router нь:
 *   • Автомат параметр шалгах (type hint: uint:id)  
 *   • route name → `link()` helper-тэй бүрэн нийцтэй  
 *   • Middleware chain-тэй зохицон ажилладаг  
 *
 * Web Layer-н философи:
 *   🔹 Dashboard-аас ялгаатай нь public веб нь  
 *   хэрэглэгчийн эрх, RBAC шалгалт шаардахгүй  
 *   → Зөвхөн localization + settings middleware-үүд ажиллана.
 *
 * @package Web\Home
 */
class HomeRouter extends Router
{
    /**
     * Public вебийн үндсэн маршрутуудыг бүртгэнэ.
     *
     * @return void
     */
    public function __construct()
    {
        // Нүүр хуудас
        $this->GET('/', [HomeController::class, 'index'])->name('home');

        // /home → индекс рүү дамжуулах alias
        $this->GET('/home', [HomeController::class, 'index']);

        // Системийн хэл солих
        $this->GET('/language/{code}', [HomeController::class, 'language'])->name('language');

        // Динамик Page (slug-аар)
        $this->GET('/page/{slug}', [HomeController::class, 'page'])->name('page');

        // Динамик News (slug-аар)
        $this->GET('/news/{slug}', [HomeController::class, 'news'])->name('news');

        // Контакт пэйж
        $this->GET('/contact', [HomeController::class, 'contact'])->name('contact');
    }
}
