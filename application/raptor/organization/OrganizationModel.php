<?php

namespace Raptor\Organization;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class OrganizationModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
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
        
        $this->setTable('organizations', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    public function fetchAllPotentialParents(): array
    {
        return $this->query(
            "SELECT * FROM {$this->getName()} WHERE (parent_id=0 OR parent_id is null) AND is_active=1"
        )->fetchAll();
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        
        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");

        $nowdate = \date('Y-m-d H:i:s');
        $this->exec("INSERT INTO $table(id,created_at,name,alias) VALUES(1,'$nowdate','System','system')");
        
        $this->setForeignKeyChecks(true);
    }
}
