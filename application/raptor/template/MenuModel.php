<?php

namespace Raptor\Template;

use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class MenuModel extends MultiModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('parent_id', 'bigint', 8, 0),
            new Column('icon', 'varchar', 64),
            new Column('href', 'varchar', 255),
            new Column('alias', 'varchar', 64),
            new Column('permission', 'varchar', 128),
            new Column('position', 'smallint', 2, 100),
            new Column('is_visible', 'tinyint', 1, 1),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setContentColumns([new Column('title', 'varchar', 128)]);
        
        $this->setTable('raptor_menu', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);

        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        
        $path = \dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($path == '\\' || $path == '/' || $path == '.') {
            $path = '';
        }
        
        $contents_id = $this->insert(
            ['position' => '200'],
            ['mn' => ['title' => 'Агуулгууд'], 'en' => ['title' => 'Contents']]
        );
        if ($contents_id != false) {            
            $this->insert(
                [
                    'parent_id' => $contents_id, 'position' => '250',
                    'alias' => 'system', 'permission' => 'system_content_index',
                    'icon' => 'bi bi-book-half', 'href' => "$path/dashboard/pages"
                ],
                ['mn' => ['title' => 'Хуудсууд'], 'en' => ['title' => 'Pages']]
            );
            $this->insert(
                [
                    'parent_id' => $contents_id, 'position' => '260',
                    'alias' => 'system', 'permission' => 'system_content_index',
                    'icon' => 'bi bi-newspaper', 'href' => "$path/dashboard/news"
                ],
                ['mn' => ['title' => 'Мэдээнүүд'], 'en' => ['title' => 'News']]
            );
            $this->insert(
                [
                    'parent_id' => $contents_id, 'position' => '270',
                    'alias' => 'system', 'permission' => 'system_content_index',
                    'icon' => 'bi bi-folder', 'href' => "$path/dashboard/files"
                ],
                ['mn' => ['title' => 'Файлууд'], 'en' => ['title' => 'Files']]
            );
            $this->insert(
                [
                    'parent_id' => $contents_id, 'position' => '280',
                    'alias' => 'system', 'permission' => 'system_localization_index',
                    'icon' => 'bi bi-translate', 'href' => "$path/dashboard/localization"
                ],
                ['mn' => ['title' => 'Нутагшуулалт'], 'en' => ['title' => 'Localization']]
            );
            $this->insert(
                [
                    'parent_id' => $contents_id, 'position' => '290',
                    'alias' => 'system', 'permission' => 'system_templates_index',
                    'icon' => 'bi bi-layout-wtf', 'href' => "$path/dashboard/references"
                ],
                ['mn' => ['title' => 'Лавлах хүснэгтүүд'], 'en' => ['title' => 'Reference Tables']]
            );
            $this->insert(
                [
                    'parent_id' => $contents_id, 'position' => '295',
                    'alias' => 'system', 'permission' => 'system_content_settings',
                    'icon' => 'bi bi-gear-wide-connected', 'href' => "$path/dashboard/settings"
                ],
                ['mn' => ['title' => 'Тохируулгууд'], 'en' => ['title' => 'Settings']]
            );
        }

        $system_id = $this->insert(
            ['position' => '300'],
            ['mn' => ['title' => 'Систем'], 'en' => ['title' => 'System']]
        );
        if ($system_id != false) {
            $this->insert(
                [
                    'parent_id' => $system_id, 'position' => '310',
                    'permission' => 'system_user_index',
                    'icon' => 'bi bi-people-fill', 'href' => "$path/dashboard/users"
                ],
                ['mn' => ['title' => 'Хэрэглэгчид'], 'en' => ['title' => 'Users']]
            );
            $this->insert(
                [
                    'parent_id' => $system_id, 'position' => '320',
                    'permission' => 'system_organization_index',
                    'icon' => 'bi bi-building', 'href' => "$path/dashboard/organizations"
                ],
                ['mn' => ['title' => 'Байгууллагууд'], 'en' => ['title' => 'Organizations']]
            );
            $this->insert(
                [
                    'parent_id' => $system_id, 'position' => '340',
                    'permission' => 'system_logger',
                    'icon' => 'bi bi-list-stars', 'href' => "$path/dashboard/logs"
                ],
                ['mn' => ['title' => 'Хандалтын протокол'], 'en' => ['title' => 'Access logs']]
            );
        }
            
        $this->setForeignKeyChecks(true);
    }
}
