<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

/**
 * LocalizationController
 *
 * Нутагшуулалтын модульд ашиглагдах хэл ба текстүүдийн нийт жагсаалтыг
 * админд үзүүлдэг controller.
 *
 * Гол үүрэг:
 * ----------
 * 1) DB доторх "localization_text_*_content" хүснэгтүүдийг автоматаар илрүүлнэ.
 * 2) TextInitial классын seed method-уудыг илрүүлж table нэрийг жагсаалтад нэмнэ.
 *    Ингэхдээ хүснэгтүүд DB-д байхгүй байсан ч:
 *       TextModel::setTable() → __initial() автоматаар ажиллаж
 *       тухайн хүснэгтүүдийг шинээр үүсгэнэ.
 *    Дараа нь TextInitial::$table() seed өгөгдлийг populate хийнэ.
 * 3) TextModel ашиглан бүх текстүүдийг уншиж бүртгэл болгон дамжуулна.
 * 4) LanguageModel ашиглан идэвхтэй хэлний жагсаалтыг авч ирнэ.
 * 5) localization-index.html Twig dashboard руу өгөгдөл дамжуулж render хийнэ.
 * 6) RBAC эрх шалгана (system_localization_index).
 *
 * Энэ controller нь Localization module-ийн үндсэн "index" буюу
 * хэл + текстүүдийн төв хяналтын самбар юм.
 */
class LocalizationController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Localization index - хэл болон бүх текстийн бүлэг (table group)-ийн жагсаалтыг харуулна.
     *
     * Ажиллах дараалал:
     * -----------------
     * 1) Хэрэглэгчийн эрхийг шалгана ("system_localization_index")
     * 2) DB-ээс "localization_text_%_content" гэх бүх хүснэгтийг хайна.
     * 3) Хүснэгтийн нэрийг цэвэрлэж table нэрийн жагсаалт бүрдүүлнэ.
     * 4) TextInitial класс дахь seed функцуудыг илрүүлнэ.
     *    Хэрэв тухайн нэртэй текстийн хүснэгт (localization_text_{name})
     *    өгөгдлийн санд хараахан үүсээгүй байсан бол:
     *        - TextModel::setTable() дуудагдах үед
     *        - TextModel::__initial() автоматаар ажиллаж
     *        - шаардлагатай үндсэн хүснэгт (+ түүний *_content хүснэгт)-үүдийг бодитоор үүсгэнэ
     *        - дараа нь TextInitial::$table() seed функц ажиллаж анхны өгөгдөл нэмнэ.
     *    Ингэснээр хүснэгт DB-д байгаагүй ч seed function байхад систем нь
     *    тухайн хүснэгтийг бүрэн үүсгээд ашиглахад бэлэн болгодог.
     * 5) TextModel ашиглан хүснэгт тус бүрийн идэвхтэй текстүүдийг уншина.
     * 6) LanguageModel ашиглан идэвхтэй хэлний жагсаалтыг уншина.
     * 7) Twig dashboard руу өгөгдлийг дамжуулж UI-г render хийнэ.
     *
     * Хэрэв алдаа гарвал:
     * ------------------
     * dashboardProhibited() ашиглан алдааны dashboard үзүүлнэ.
     *
     * Лог бичилт:
     * ----------
     * indolog('localization', LogLevel::NOTICE, ...)
     * - хэрэглэгч localization index-г үзсэн тухай лог үлдээнэ.
     *
     * @return void
     */
    public function index()
    {        
        try {
            // Хэрэглэгч эрхгүй бол → алдаа
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            // DB доторх орчуулгын content хүснэгтүүдийг хайх
            if ($this->getDriverName() == 'pgsql') {
                // PostgreSQL хувилбар
                $query =
                    'SELECT tablename FROM pg_catalog.pg_tables ' .
                    "WHERE schemaname != 'pg_catalog'
                       AND schemaname != 'information_schema'
                       AND tablename like 'localization_text_%_content'";
            } elseif ($this->getDriverName() == 'sqlite') {
                // SQLite хувилбар
                $query = "SELECT name as tablename FROM sqlite_master WHERE type='table' AND name LIKE 'localization_text_%_content'";
            } else {
                // MySQL хувилбар
                $query = 'SHOW TABLES LIKE ' . $this->quote('localization_text_%_content');
            }
            $text_content_tables = $this->query($query)->fetchAll();
            $text_tables = [];

            // DB-д байгаа орчуулгын хүснэгтийн нэрийг боловсруулах
            // localization_text_{name}_content → {name}
            foreach ($text_content_tables as $result) {
                $text_tables[] = \substr(
                    reset($result),
                    \strlen('localization_text_'),
                    -\strlen('_content')
                );
            }

            // TextInitial доторх seed функцуудыг илрүүлэх
            // Хүснэгт байхгүй байсан ч table-г үүсгэн dashboard дээр харуулах зорилготой.
            $text_initials = \get_class_methods(TextInitial::class);
            foreach ($text_initials as $value) {
                $text_initial = \substr($value, \strlen('localization_text_'));
                if (!empty($text_initial) && !\in_array($text_initial, $text_tables)) {
                    // Хүснэгт физикээр байхгүй байсан ч seed function байгаа бол
                    // тухайн хүснэгт жагсаалтад нэмэгдэнэ.
                    $text_tables[] = $text_initial;
                }
            }

            // Хүснэгт тус бүрийн текстүүдийг унших
            $texts = [];
            foreach ($text_tables as $table) {
                $model = new TextModel($this->pdo);
                $model->setTable($table);

                // Зөвхөн идэвхтэй текстүүдийг keyword дарааллаар
                $texts[$table] = $model->getRows([
                    'WHERE'    => 'p.is_active=1',
                    'ORDER BY' => 'p.keyword'
                ]);
            }

            // Идэвхтэй хэлнүүдийг унших
            $languages = (new LanguageModel($this->pdo))->getRows(['WHERE' => 'is_active=1']);

            // Localization dashboard render хийх
            $dashboard = $this->twigDashboard(
                __DIR__ . '/localization-index.html',
                [
                    'languages' => $languages,
                    'texts'     => $texts
                ]
            );
            $dashboard->set('title', $this->text('localization'));
            $dashboard->render();

            // Аудитын лог үлдээх
            $this->indolog(
                'localization',
                LogLevel::NOTICE,
                'Хэл ба Текстүүдийн жагсаалтыг үзэж байна',
                ['action' => 'localization-index']
            );
        } catch (\Throwable $err) {
            // Алдаа гарвал Dashboard хэлбэрээр харуулна
            $this->dashboardProhibited(
                $err->getMessage(),
                $err->getCode()
            )->render();
        }
    }
}
