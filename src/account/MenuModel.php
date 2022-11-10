<?php

namespace Indoraptor\Account;

use PDO;

use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class MenuModel extends MultiModel
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns(array(
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('parent_id', 'bigint', 8, 0),
            new Column('icon', 'varchar', 64),
            new Column('href', 'varchar', 255),
            new Column('alias', 'varchar', 64),
            new Column('permission', 'varchar', 128),
            new Column('position', 'int', 8, 100),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ));
        
        $this->setContentColumns(array(new Column('title', 'varchar', 128)));
        
        $this->setTable('account_menu', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    function __initial()
    {
        parent::__initial();
        
        $table = $this->getName();
        
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
}
