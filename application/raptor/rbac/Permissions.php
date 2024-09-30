<?php

namespace Raptor\RBAC;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class Permissions extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
           (new Column('name', 'varchar', 128))->unique()->notNull(),
            new Column('module', 'varchar', 128, 'general'),
            new Column('description', 'varchar', 255),
           (new Column('alias', 'varchar', 64))->notNull(),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setTable('rbac_permissions', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        
        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        
        $nowdate = \date('Y-m-d H:i:s');
        $query =
            "INSERT INTO $table(created_at,alias,module,name,description) "
            . "VALUES('$nowdate','system','log','logger',''),"
            . "('$nowdate','system','user','rbac',''),"
            . "('$nowdate','system','user','user_index',''),"
            . "('$nowdate','system','user','user_insert',''),"
            . "('$nowdate','system','user','user_update',''),"
            . "('$nowdate','system','user','user_delete',''),"
            . "('$nowdate','system','user','user_organization_set',''),"
            . "('$nowdate','system','organization','organization_index',''),"
            . "('$nowdate','system','organization','organization_insert',''),"
            . "('$nowdate','system','organization','organization_update',''),"
            . "('$nowdate','system','organization','organization_delete',''),"
            . "('$nowdate','system','content','content_settings',''),"
            . "('$nowdate','system','content','content_index',''),"
            . "('$nowdate','system','content','content_insert',''),"
            . "('$nowdate','system','content','content_publish',''),"
            . "('$nowdate','system','content','content_delete',''),"
            . "('$nowdate','system','localization','localization_index',''),"
            . "('$nowdate','system','localization','localization_insert',''),"
            . "('$nowdate','system','localization','localization_update',''),"
            . "('$nowdate','system','localization','localization_delete','')";
        $this->exec($query);
        
        $this->setForeignKeyChecks(true);
    }
}