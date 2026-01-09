<?php

namespace Web\Template;

use codesaur\Template\TwigTemplate;
use Raptor\Content\PagesModel;

/**
 * Class TemplateController
 * ---------------------------------------------------------------
 * 🌐 Indoraptor Framework - Web UI Template Controller
 *
 * Энэ контроллер нь вэб сайтын бүх үндсэн layout (index.html) болон
 * динамик контентуудыг TwigTemplate ашиглан нэгтгэж рендерлэх үүрэгтэй.
 *
 * ✨ Үндсэн боломжууд:
 * ---------------------------------------------------------------
 * ✔ Вэб хуудсын үндсэн загвар (`index.html`)-ийг ачаалах  
 * ✔ Контент template-ийг index layout дотор оруулж нэгтгэх  
 * ✔ System settings → footer, SEO, branding гэх мэт template хувьсагчид  
 * ✔ Олон түвшинтэй Main Menu (dynamic page tree) үүсгэх  
 * ✔ Important Menu (footer-ийн товч меню) үүсгэх  
 *
 * Тухайн сайт нь олон хэл дээр ажиллах ба `PagesModel` дээр суурилсан
 * харагдах, нийтлэгдсэн контентуудыг navigation болгон хувиргана.
 *
 * @package Web\Template
 */
class TemplateController extends \Raptor\Controller
{
    /**
     * Template layout-г контенттой нь нэгтгэж TwigTemplate объект буцаана.
     *
     * Ажиллах дараалал:
     * 1) index.html layout-г ачаална  
     * 2) content template-г ачааж index layout дотор `{{ content }}` хувьсагчид суулгана  
     * 3) System settings (favicon, title, description…) дамжуулна  
     * 4) Main Menu болон Important Menu-г тухайн хэл дээр динамик байдлаар үүсгэнэ  
     *
     * @param string $template Контентын Twig template файл (жишээ: page.html)
     * @param array  $vars     Контент template-д дамжуулах хувьсагчид
     *
     * @return TwigTemplate Web-ийн бүрэн layout-тэй рендерлэхэд бэлэн объект
     */
    public function template(string $template, array $vars = []): TwigTemplate
    {
        $index = $this->twigTemplate(__DIR__ . '/index.html');
        $index->set('content', $this->twigTemplate($template, $vars));

        // System settings (favicon, SEO, branding…)
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $index->set($key, $value);
        }

        // Navigation menu (сонгосон хэлээр)
        $code = $this->getLanguageCode();
        $index->set('main_menu', $this->getMainMenu($code));
        $index->set('important_menu', $this->getImportantMenu($code));

        return $index;
    }

    /**
     * Вэб сайтын Main Menu-г олон түвшний бүтэцтэйгээр үүсгэнэ.
     *
     * Энэ меню нь хоосон parent → child хэлбэрийн Page бүтэц дээр суурилдаг:
     *
     * - type != 'special-page'
     * - published = 1
     * - is_active = 1
     *
     * @param string $code Тухайн хэлний код (mn, en...)
     * @return array Бүтэцлэгдсэн меню (submenu дотор дахин хүүхэд элементтэй)
     */
    public function getMainMenu(string $code): array
    {
        $pages = [];
        $pages_table = (new PagesModel($this->pdo))->getName();
        $pages_query =
            'SELECT id, parent_id, title, link ' .
            "FROM $pages_table " .
            "WHERE code=:code AND is_active=1 AND published=1 AND type!='special-page' " .
            'ORDER BY position, id';
        $stmt = $this->prepare($pages_query);
        $stmt->bindParam(':code', $code, \PDO::PARAM_STR);
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            while ($row = $stmt->fetch()) {
                $pages[$row['id']] = $row;
            }
        }

        // Parent-child олон түвшний навигаци үүсгэнэ
        return $this->buildMenu($pages);
    }

    /**
     * Parent-child бүтэцтэй олон түвшний менюг рекурсив байдлаар үүсгэх.
     *
     * @param array $pages Page жагсаалт
     * @param int   $parent_id Эхлэл ID (default: 0)
     * @return array Submenu бүтэц
     */
    private function buildMenu(array $pages, int $parent_id = 0): array
    {
        $navigation = [];
        foreach ($pages as $element) {
            if ($element['parent_id'] == $parent_id) {
                // Хүүхэд submenu байвал оноох
                $children = $this->buildMenu($pages, $element['id']);
                if ($children) {
                    $element['submenu'] = $children;
                }
                $navigation[$element['id']] = $element;
            }
        }
        return $navigation;
    }

    /**
     * Important Menu-г авах (footer-ийн чухал холбоосууд)
     *
     * type='important-menu' гэж тэмдэглэсэн контентуудыг энд гаргана.
     *
     * @param string $code Хэлний код
     * @return array Footer-д харуулах богино меню
     */
    public function getImportantMenu(string $code): array
    {
        $pages = [];
        $pages_table = (new PagesModel($this->pdo))->getName();
        $pages_query =
            'SELECT id, title, link ' .
            "FROM $pages_table " .
            "WHERE code=:code AND is_active=1 AND published=1 AND type='important-menu' " .
            'ORDER BY position, id';
        $stmt = $this->prepare($pages_query);
        $stmt->bindParam(':code', $code, \PDO::PARAM_STR);
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            while ($row = $stmt->fetch()) {
                $pages[$row['id']] = $row;
            }
        }
        return $pages;
    }
}
