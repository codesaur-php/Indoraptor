<?php

namespace Dashboard\Home;

use Psr\Log\LogLevel;

/**
 * Class HomeController
 * ----------------------------------------------------------------------
 * Dashboard module-ийн үндсэн Controller.
 *
 * Энэ контроллер нь системд логин хийж орсон хэрэглэгчдэд
 * харагдах “Нүүр хуудас / Overview” хэсгийг рендерлэх
 * үндсэн entry point юм.
 *
 * Одоогийн функц:
 *   - home.html template-ийг Dashboard layout дээр ачаалж үзүүлэх
 *   - Үйлдлийн лог (indolog) бүртгэх
 *
 * Хөгжүүлэгчид зориулсан өргөтгөх боломж:
 * ------------------------------------------------------------------
 * HomeController бол Dashboard-ийн хамгийн эхний харагдах хэсэг учир:
 *   ✔ Статистик widget-үүд
 *   ✔ Шинэ мэдэгдэл, системийн төлөв байдлын мэдээлэл
 *   ✔ Chart.js / ApexCharts график
 *   ✔ Хэрэглэгчийн role-д тохирсон overview data
 *   ✔ Сүүлийн үйлдлүүдийн log-ууд
 *   ✔ Custom dashboard cards
 * зэргийг энд нэмэн хөгжүүлэх боломжтой.
 *
 * Framework-ийн бүтцийн хувьд:
 *   - twigDashboard() → Dashboard layout ашиглан контент ачаана
 *   - home.html → зөвхөн контент хэсэгт байрлах view
 *   - indolog() → системийн үйлдлийн лог бичих стандарт функц
 *
 * @package Dashboard\Home
 */
class HomeController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Dashboard-ийн үндсэн нүүр хуудас руу хандах функц.
     * ------------------------------------------------------------------
     * 1) home.html template-г Dashboard layout-той хамт render хийнэ
     * 2) Хэрэглэгчийн үйлдлийг “dashboard” лог хүснэгтэд NOTICE түвшинд бичиж байна
     *
     * @return void
     */
    public function index()
    {
        // Нүүр хуудасны template-г Dashboard layout ашиглан үзүүлэх
        $this->twigDashboard(__DIR__ . '/home.html')->render();

        // Үйлдлийг лог бүртгэлд хадгалах
        $this->indolog('dashboard', LogLevel::NOTICE, 'Нүүр хуудсыг уншиж байна');
    }
}
