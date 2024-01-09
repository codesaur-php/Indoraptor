<?php

namespace Indoraptor\Auth;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class OrganizationUserModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('account_id', 'bigint', 8),
            new Column('organization_id', 'bigint', 8),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setTable('indo_organization_users', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    public function retrieve(int $organization_id, int $account_id): array|false
    {
        $stmt = $this->prepare(
            "SELECT * FROM {$this->getName()} WHERE account_id=:1 AND organization_id=:2 AND is_active=1 LIMIT 1");
        $stmt->bindParam(':1', $account_id, \PDO::PARAM_INT);
        $stmt->bindParam(':2', $organization_id, \PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() == 1) {
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
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
    
    protected function __initial()
    {
        $table = $this->getName();
        
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_account_id FOREIGN KEY (account_id) REFERENCES rbac_accounts(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_organization_id FOREIGN KEY (organization_id) REFERENCES indo_organizations(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
        
        $nowdate = \date('Y-m-d H:i:s');
        $this->exec("INSERT INTO $table(id,created_at,account_id,organization_id) VALUES(1,'$nowdate',1,1)");
    }
}
