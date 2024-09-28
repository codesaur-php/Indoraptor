<?php

namespace Raptor\Organization;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class OrganizationUserModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('user_id', 'bigint', 8),
            new Column('organization_id', 'bigint', 8),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setTable('organizations_users', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    public function retrieve(int $organization_id, int $user_id): array|false
    {
        $stmt = $this->prepare(
            "SELECT * FROM {$this->getName()} WHERE user_id=:user AND organization_id=:org AND is_active=1 LIMIT 1");
        $stmt->bindParam(':user', $user_id, \PDO::PARAM_INT);
        $stmt->bindParam(':org', $organization_id, \PDO::PARAM_INT);
        if ($stmt->execute() && $stmt->rowCount() == 1) {
            $record = $stmt->fetch();
            foreach ($this->getColumns() as $column) {
                if (isset($record[$column->getName()])) {
                    if ($column->isInt()) {
                        $record[$column->getName()] = (int) $record[$column->getName()];
                    }
                }
            }
            return $record;
        }
        
        return false;
    }
    
    public function fetchAllOrgsByUser(int $user_id): array
    {
        return $this->query(
            "SELECT id,organization_id FROM {$this->getName()} WHERE user_id=$user_id AND is_active=1"
        )->fetchAll();
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        
        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_organization_id FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        
        $nowdate = \date('Y-m-d H:i:s');
        $this->exec("INSERT INTO $table(id,created_at,user_id,organization_id) VALUES(1,'$nowdate',1,1)");
        
        $this->setForeignKeyChecks(true);
    }
}
