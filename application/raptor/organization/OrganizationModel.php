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
           (new Column('id', 'bigint'))->primary(),
            new Column('parent_id', 'bigint'),
           (new Column('name', 'varchar', 255))->unique(),
            new Column('logo', 'varchar', 255),
           (new Column('alias', 'varchar', 64))->default('common'),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);
        
        $this->setTable('organizations');
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
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);

        $nowdate = \date('Y-m-d H:i:s');
        $this->exec("INSERT INTO $table(created_at,name,alias) VALUES('$nowdate','System','system')");        
    }
    
    public function insert(array $record): array|false
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record);
    }
}
