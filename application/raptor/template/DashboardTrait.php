<?php

namespace Raptor\Template;

use codesaur\Template\TwigTemplate;

use Raptor\User\UsersModel;

/**
 * DashboardTrait - Dashboard UI руу контент байрлуулах,
 * permission-гүй үед alert/modal үзүүлэх, хэрэглэгчийн
 * sidemenu-г динамикаар үүсгэх зэрэг нийтлэг dashboard
 * функциональ байдлыг Controller-д ашиглуулах зориулалттай trait.
 *
 * Үндсэн үүрэг:
 * ───────────────────────────────────────────────────────────────
 *  - twigDashboard()  
 *      → Dashboard layout (dashboard.html) дотор контент оруулах
 *
 *  - dashboardProhibited()  
 *      → Хэрэглэгч эрхгүй үед dashboard орчинд permission alert үзүүлэх
 *
 *  - modalProhibited()  
 *      → Modal орчинд permission alert үзүүлэх
 *
 *  - retrieveUsersDetail()  
 *      → Audit log, organization mapping зэрэг UI-д хэрэглэгчийн
 *         товч мэдээллийн жагсаалтыг авах
 *
 *  - getUserMenu()  
 *      → Permission, is_active, is_visible, organization alias
 *         зэрэг нөхцөлүүдээр хэрэглэгчийн sidemenu-г бүрдүүлэх
 *
 * Энэ trait нь Raptor\Controller-тэй цуг ажиллаж,
 * Indoraptor Dashboard UI-ийн суурь rendering pipeline-г бүрдүүлнэ.
 */
trait DashboardTrait
{
    /**
     * Dashboard Layout ашиглан контент рендерлэх гол метод.
     *
     * Энэхүү функц нь бүх Dashboard төрлийн хуудасны мастер layout юм.
     * Хэрэглэгчийн харагдах UI бүтэц (sidebar, user info, content area)
     * бүгдийг энд төвлөрүүлж, динамикаар бүрдүүлдэг.
     *
     * Процесс:
     * ───────────────────────────────────────────────────────────────
     * 1) `dashboard.html` мастер layout-ийг twigTemplate() ашиглан ачаална.
     *
     * 2) Хэрэглэгчийн зөвшөөрөлд (RBAC) тулгуурлан харагдах ёстой
     *    sidemenu-г getUserMenu() функцээр тооцож → `sidemenu` хувьсагчид онооно.
     *
     * 3) Контент хэсэгт харуулах тухайн хуудасны template-г
     *    twigTemplate($template, $vars) дуудаж → `content` болгон оруулна.
     *    (Жич: Контент template нь зөвхөн `<main>` хэсэг дотор байрлана.)
     *
     * 4) Системийн тохируулгууд (`settings` аттрибут) - тухайлбал:
     *    footer мэдээлэл, брэндингийн өгөгдөл, favicon, logo зэрэг
     *    layout түвшинд хэрэгтэй бүх өгөгдлийг `$dashboard->set()` ашиглан нэг нэгээр нь оруулна.
     *
     * Товчхондоо:
     * ───────────────────────────────────────────────────────────────
     *  ➤ Dashboard layout + Dynamic sidebar + Dynamic content + System settings 
     *  → нэг TwigTemplate объект болж буцна.
     *
     * @param string $template  Контент template-ийн файл зам
     * @param array  $vars      Контент template-д дамжуулах хувьсагчид
     *
     * @return TwigTemplate     Бүрэн бэлтгэгдсэн Dashboard-ийн view объект
     */
    public function twigDashboard(string $template, array $vars = []): TwigTemplate
    {
        $dashboard = $this->twigTemplate(__DIR__ . '/dashboard.html');
        $dashboard->set('sidemenu', $this->getUserMenu());
        $dashboard->set('content', $this->twigTemplate($template, $vars));        
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $dashboard->set($key, $value);
        }
        return $dashboard;
    }

    /**
     * Dashboard орчинд permission-гүй үед alert харуулах.
     *
     * - Хариу код тохируулна (403 гэх мэт)
     * - Dashboard layout дотор "no-permission" контент байрлуулна
     *
     * @param string|null    $alert Alert текст
     * @param int|string     $code  HTTP статус код
     *
     * @return TwigTemplate
     */
    public function dashboardProhibited(?string $alert = null, int|string $code = 0): TwigTemplate
    {
        $this->headerResponseCode($code);

        return $this->twigDashboard(
            __DIR__ . '/alert-no-permission.html',
            ['alert' => $alert ?? $this->text('system-no-permission')]
        );
    }

    /**
     * Modal орчинд permission-гүй үед харуулах template.
     *
     * - Dashboard layout ашиглахгүй
     * - Зөвхөн modal-д тохирох жижиг template буцаана
     *
     * @param string|null $alert
     * @param int|string  $code
     *
     * @return TwigTemplate
     */
    public function modalProhibited(?string $alert = null, int|string $code = 0): TwigTemplate
    {
        $this->headerResponseCode($code);

        return new TwigTemplate(
            __DIR__ . '/modal-no-permission.html',
            [
                'alert' => $alert ?? $this->text('system-no-permission'),
                'close' => $this->text('close')
            ]
        );
    }

    /**
     * Хэрэглэгчдийн товч мэдээллийн жагсаалт авах (audit trail-д ашиглагдана).
     *
     * Оролт:
     *   - Хэдэн ч user_id дамжуулж болно.
     *   - user_id = null эсвэл ямар ч ID дамжуулаагүй бол:
     *         → users хүснэгт дэх **бүх хэрэглэгчийн** мэдээллийг авна.
     *
     * Гаралт:
     *   [
     *      4 => "batka » Бат Эрдэнэ (bat@example.com)",
     *      9 => "saraa » Сараа Мөнх (saraa@example.com)",
     *   ]
     *
     * Алдаа гарвал хоосон массив буцаана.
     *
     * @param int|null ...$ids
     * @return array [user_id => label]
     */
    protected function retrieveUsersDetail(?int ...$ids)
    {
        $users = [];
        
        try {
            $had_condition = !empty($ids);
            $table = (new UsersModel($this->pdo))->getName();
            $select_users =
                "SELECT id, username, first_name, last_name, email FROM $table";
            // WHERE нөхцөл боловсруулах
            if ($had_condition) {
                $ids = \array_filter($ids, fn($v) => $v !== null);
                if (empty($ids)) {
                    throw new \InvalidArgumentException(__FUNCTION__ . ': invalid arguments!');
                }
                \array_walk($ids, fn(&$v) => $v = "id=$v");
                $select_users .= ' WHERE ' . \implode(' OR ', $ids);
            }

            $pdo_stmt = $this->prepare($select_users);
            if ($pdo_stmt->execute()) {
                while ($row = $pdo_stmt->fetch()) {
                    $users[$row['id']] =
                        "{$row['username']} » {$row['first_name']} {$row['last_name']} ({$row['email']})";
                }
            }
        } catch (\Throwable) {
            // Хүсвэл алдааг development үед логлох боломжтой
        }
        
        return $users;
    }

    /**
     * Хэрэглэгчийн sidemenu-г динамикаар үүсгэх.
     *
     * Filter-лэгдэх нөхцөлүүд:
     * ───────────────────────────────────────────────────────────────
     *  - p.is_active = 1  → идэвхтэй меню
     *  - p.is_visible = 1 → харагдах боломжтой
     *  - Organization alias тохирох эсэх:
     *        menu.alias != current_user.organization.alias → skip
     *  - Permission заасан бол:
     *        !isUserCan(menu.permission) → skip
     *  - Localization: title нь тухайн хэл дээр байх ёстой
     *
     * Menu бүтэц:
     * ───────────────────────────────────────────────────────────────
     *  [
     *      parent_menu_id => [
     *          'title' => '...',
     *          'submenu' => [
     *              ['title' => '...', 'link' => '...', ...],
     *              ...
     *          ]
     *      ],
     *      ...
     *  ]
     *
     * Эцэст нь submenu хоосон parent-уудыг устгана.
     *
     * @return array Sidemenu-н бүтэц
     */
    public function getUserMenu(): array
    {
        $sidemenu = [];

        try {
            $alias = $this->getUser()->organization['alias'];
            
            $model = new MenuModel($this->pdo);
            $rows = $model->getRowsByCode(
                $this->getLanguageCode(),
                [
                    'ORDER BY' => 'p.position',
                    'WHERE'    => 'p.is_active=1 AND p.is_visible=1'
                ]
            );
            foreach ($rows as $row) {
                // Localization title авах
                $title = $row['localized']['title'] ?? null;

                // Organization alias filter
                if (!empty($row['alias']) && $alias !== $row['alias']) {
                    continue;
                }

                // Permission filter
                if (!empty($row['permission'])
                    && !$this->isUserCan($row['permission'])) {
                    continue;
                }

                // Parent menu
                if ($row['parent_id'] == 0) {
                    if (!isset($sidemenu[$row['id']])) {
                        $sidemenu[$row['id']] = ['title' => $title, 'submenu' => []];
                    } else {
                        $sidemenu[$row['id']]['title'] = $title;
                    }
                }
                // Child menu
                else {
                    unset($row['localized']);
                    $row['title'] = $title;

                    if (!isset($sidemenu[$row['parent_id']])) {
                        $sidemenu[$row['parent_id']] =
                            ['title' => '', 'submenu' => [$row]];
                    } else {
                        $sidemenu[$row['parent_id']]['submenu'][] = $row;
                    }
                }
            }

            // submenu хоосон parent-уудыг устгах
            foreach ($sidemenu as $key => $rows) {
                if (empty($rows['submenu'])) {
                    unset($sidemenu[$key]);
                }
            }
        } catch (\Throwable $e) {
            $this->errorLog($e);
        }

        return $sidemenu;
    }
}
