<?php

namespace Indoraptor\Auth;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class OrganizationModel extends Model
{
    function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('parent_id', 'bigint', 8),
           (new Column('name', 'varchar', 255))->unique(),
            new Column('logo', 'varchar', 255),
            new Column('alias', 'varchar', 64, 'common'),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setTable('indo_organizations', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    function __initial()
    {
        parent::__initial();

        $table = $this->getName();
        
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);

        if ($table != 'indo_organizations') {
            return;
        }
        
        $nowdate = date('Y-m-d H:i:s');
        $this->exec("INSERT INTO $table(id,created_at,name,alias) VALUES(1,'$nowdate','System','system')");
    }
}
