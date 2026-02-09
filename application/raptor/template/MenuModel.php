<?php

namespace Raptor\Template;

use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

/**
 * Class MenuModel
 *
 * Indoraptor Framework-ийн Dashboard хэсгийн үндсэн
 * менютэй холбоотой өгөгдлийг удирдах LocalizedModel.
 *
 * Онцлог:
 *  - Олон хэл дээрх title талбар LocalizedModel-аар автоматаар удирдагдана.
 *  - parent/child бүтэц бүхий модульчлагдсан цэс зохион байгуулалттай.
 *  - Permission-тэй уялдаж тухайн хэрэглэгчийн харж болох менюг
 *    динамикаар шүүн харуулдаг.
 *  - Хамгийн эхний удаа Dashboard Application суух/ачаалах үед (__initial) анхны системийн меню үүснэ.
 *
 * Хүснэгт: raptor_menu + raptor_menu_content (LocalizedModel)
 *
 * @package Raptor\Template
 */
class MenuModel extends LocalizedModel
{
    /**
     * MenuModel constructor.
     *
     * @param \PDO $pdo  PDO instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        // --- Үндсэн баганаууд ---
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('parent_id', 'bigint'))->default(0),             // Эцэг меню ID
            new Column('icon', 'varchar', 64),                          // Bootstrap Icons нэр
            new Column('href', 'varchar', 255),                         // Линк
            new Column('alias', 'varchar', 64),                         // Меню аль байгууллагынх вэ (organization alias)
            new Column('permission', 'varchar', 128),                   // RBAC permission код
           (new Column('position', 'smallint'))->default(100),          // Дараалал
           (new Column('is_visible', 'tinyint'))->default(1),           // UI дээр харагдах эсэх
           (new Column('is_active', 'tinyint'))->default(1),            // Идэвхтэй эсэх
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);

        // --- Олон хэл дээрх контент талбар ---
        $this->setContentColumns([
            new Column('title', 'varchar', 128)  // Менюгийн харагдах нэр
        ]);

        $this->setTable('raptor_menu');
    }

    /**
     * Анхны тохиргоо (__initial)
     *
     * Энэ функц нь:
     *   - FK хамаарлуудыг зурж өгнө
     *   - Dashboard-д харагдах үндсэн меню, дэд менюг автоматаар seed хийнэ:
     *        Contents → Pages / News / Files / Localization / Reference tables / Settings / Website
     *        System → Users / Organizations / Logs
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();

        // SQLite нь ALTER TABLE ... ADD CONSTRAINT дэмжихгүй
        // MySQL/PostgreSQL дээр л FK constraint нэмнэ
        if ($this->getDriverName() != 'sqlite') {
            $this->setForeignKeyChecks(false);

            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
            // created_by / updated_by → Users FK
            $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by 
                         FOREIGN KEY (created_by) REFERENCES $users(id) 
                         ON DELETE SET NULL ON UPDATE CASCADE");
            $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by 
                         FOREIGN KEY (updated_by) REFERENCES $users(id) 
                         ON DELETE SET NULL ON UPDATE CASCADE");

            $this->setForeignKeyChecks(true);
        }

        // --- Base URL (subfolder дотор суусан тохиолдолд зөв линк үүсгэх) ---
        $path = \dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($path == '\\' || $path == '/' || $path == '.') {
            $path = '';
        }
        
        /**
         * ----------------------------------------
         * 1. CONTENTS үндсэн хэсэг
         * ----------------------------------------
         */
        $contents = $this->insert(
            ['position' => '200'],
            ['mn' => ['title' => 'Агуулгууд'], 'en' => ['title' => 'Contents']]
        );
        if (isset($contents['id'])) {
            // Public веб сайт руу очих линк
            $this->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '201',
                    'alias' => 'system',
                    'icon' => 'bi bi-rocket-takeoff',
                    'href' => "$path/home\" target=\"__blank"
                ],
                ['mn' => ['title' => 'Веблүү очих'], 'en' => ['title' => 'Visit Website']]
            );
            // Хуудсууд
            $this->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '250',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-book-half',
                    'href' => "$path/dashboard/pages/nav"
                ],
                ['mn' => ['title' => 'Хуудсууд'], 'en' => ['title' => 'Pages']]
            );
            // Мэдээнүүд
            $this->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '260',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-newspaper',
                    'href' => "$path/dashboard/news"
                ],
                ['mn' => ['title' => 'Мэдээнүүд'], 'en' => ['title' => 'News']]
            );
            // Файлууд
            $this->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '270',
                    'alias' => 'system',
                    'permission' => 'system_content_index',
                    'icon' => 'bi bi-folder',
                    'href' => "$path/dashboard/files"
                ],
                ['mn' => ['title' => 'Файлууд'], 'en' => ['title' => 'Files']]
            );
            // Localization
            $this->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '280',
                    'alias' => 'system',
                    'permission' => 'system_localization_index',
                    'icon' => 'bi bi-translate',
                    'href' => "$path/dashboard/localization"
                ],
                ['mn' => ['title' => 'Нутагшуулалт'], 'en' => ['title' => 'Localization']]
            );
            // Reference tables
            $this->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '290',
                    'alias' => 'system',
                    'permission' => 'system_templates_index',
                    'icon' => 'bi bi-layout-wtf',
                    'href' => "$path/dashboard/references"
                ],
                ['mn' => ['title' => 'Лавлах хүснэгтүүд'], 'en' => ['title' => 'Reference Tables']]
            );
            // Settings
            $this->insert(
                [
                    'parent_id' => $contents['id'],
                    'position' => '295',
                    'alias' => 'system',
                    'permission' => 'system_content_settings',
                    'icon' => 'bi bi-gear-wide-connected',
                    'href' => "$path/dashboard/settings"
                ],
                ['mn' => ['title' => 'Тохируулгууд'], 'en' => ['title' => 'Settings']]
            );
        }

        /**
         * ----------------------------------------
         * 2. SYSTEM үндсэн хэсэг
         * ----------------------------------------
         */
        $system = $this->insert(
            ['position' => '300'],
            ['mn' => ['title' => 'Систем'], 'en' => ['title' => 'System']]
        );
        if (isset($system['id'])) {
            // Хэрэглэгчид
            $this->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '310',
                    'permission' => 'system_user_index',
                    'icon' => 'bi bi-people-fill',
                    'href' => "$path/dashboard/users"
                ],
                ['mn' => ['title' => 'Хэрэглэгчид'], 'en' => ['title' => 'Users']]
            );
            // Байгууллагууд
            $this->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '320',
                    'permission' => 'system_organization_index',
                    'icon' => 'bi bi-building',
                    'href' => "$path/dashboard/organizations"
                ],
                ['mn' => ['title' => 'Байгууллагууд'], 'en' => ['title' => 'Organizations']]
            );
            // Logs
            $this->insert(
                [
                    'parent_id' => $system['id'],
                    'position' => '340',
                    'permission' => 'system_logger',
                    'icon' => 'bi bi-list-stars',
                    'href' => "$path/dashboard/logs"
                ],
                ['mn' => ['title' => 'Хандалтын протокол'], 'en' => ['title' => 'Access logs']]
            );
        }
    }

    /**
     * Insert хийж буй үед created_at автоматаар бөглөгдөнө.
     */
    public function insert(array $record, array $content): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record, $content);
    }

    /**
     * update үед updated_at автоматаар шинэчлэгдэнэ.
     */
    public function updateById(int $id, array $record, array $content): array|false
    {
        $record['updated_at'] ??= \date('Y-m-d H:i:s');
        return parent::updateById($id, $record, $content);
    }
}
