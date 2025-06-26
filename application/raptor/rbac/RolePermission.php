<?php

namespace Raptor\RBAC;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class RolePermission extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('role_id', 'bigint'))->notNull(),
           (new Column('permission_id', 'bigint'))->notNull(),
           (new Column('alias', 'varchar', 64))->notNull(),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);
        
        $this->setTable('rbac_role_permission');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);        
        $table = $this->getName();
        $roles = (new Roles($this->pdo))->getName();
        $permissions = (new Permissions($this->pdo))->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_role_id FOREIGN KEY (role_id) REFERENCES $roles(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_permission_id FOREIGN KEY (permission_id) REFERENCES $permissions(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
    
    public function insert(array $record): array|false
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record);
    }
}
