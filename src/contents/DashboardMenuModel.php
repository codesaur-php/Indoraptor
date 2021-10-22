<?php

namespace Indoraptor\Contents;

use PDO;

use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class DashboardMenuModel extends MultiModel
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns(array(
           (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull(),
            new Column('parent_id', 'int', 20, 0),
            new Column('icon', 'varchar', 64),
            new Column('href', 'varchar', 1024),
            new Column('position', 'int', 8, 100),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 20),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 20)
        ));
        
        $this->setContentColumns(array(new Column('title', 'varchar', 128)));
        
        $this->setTable('dashboard_menu');
    }
    
    function __initial()
    {
        parent::__initial();
        
        $table = $this->getName();        
        
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_parent_id FOREIGN KEY (parent_id) REFERENCES $table(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
        
        if ($table != 'dashboard_menu') {
            return;
        }
        
        $idMain = $this->insert(array('position' => '10'), array('mn' => array('title' => 'Үндсэн'), 'en' => array('title' => 'Main')));
        $this->insert(array('parent_id' => $idMain, 'position' => '11', 'icon' => 'bi bi-house-door', 'href' => '/'), array('mn' => array('title' => 'Хянах самбар'), 'en' => array('title' => 'Dashboard')));
        $this->insert(array('parent_id' => $idMain, 'position' => '12', 'icon' => 'bi bi-house-fill', 'href' => '/'), array('mn' => array('title' => 'Нүүр хуудас'), 'en' => array('title' => 'Homepage')));

        $idContent = $this->insert(array('position' => '200'), array('mn' => array('title' => 'Агуулгууд'), 'en' => array('title' => 'Contents')));
        $this->insert(array('parent_id' => $idContent, 'position' => '210', 'icon' => 'bi bi-flag-fill', 'href' => '/language'), array('mn' => array('title' => 'Хэл'), 'en' => array('title' => 'Language')));
        $this->insert(array('parent_id' => $idContent, 'position' => '220', 'icon' => 'bi bi-translate', 'href' => '/translation'), array('mn' => array('title' => 'Орчуулга'), 'en' => array('title' => 'Translation')));
        $this->insert(array('parent_id' => $idContent, 'position' => '230', 'icon' => 'bi bi-file-post-fill', 'href' => '/templates'), array('mn' => array('title' => 'Баримт бичиг загвар'), 'en' => array('title' => 'Document Templates')));

        $idSystem = $this->insert(array('position' => '300'), array('mn' => array('title' => 'Систем'), 'en' => array('title' => 'System')));
        $this->insert(array('parent_id' => $idSystem, 'position' => '310', 'icon' => 'bi bi-people-fill', 'href' => '/accounts'), array('mn' => array('title' => 'Хэрэглэгчид'), 'en' => array('title' => 'Accounts')));
        $this->insert(array('parent_id' => $idSystem, 'position' => '320', 'icon' => 'bi bi-bank2', 'href' => '/organizations'), array('mn' => array('title' => 'Байгууллага'), 'en' => array('title' => 'Organization')));
        $this->insert(array('parent_id' => $idSystem, 'position' => '330', 'icon' => 'bi bi-mailbox2', 'href' => '/mailer'), array('mn' => array('title' => 'Шууданч'), 'en' => array('title' => 'Mail carrier')));
        $this->insert(array('parent_id' => $idSystem, 'position' => '340', 'icon' => 'bi bi-list-stars', 'href' => '/log'), array('mn' => array('title' => 'Хандалтын протокол'), 'en' => array('title' => 'Access log')));
    }
}
