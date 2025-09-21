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
           (new Column('id', 'bigint'))->primary(),
            new Column('user_id', 'bigint'),
            new Column('organization_id', 'bigint'),
            new Column('created_at', 'timestamp'),
            new Column('created_by', 'bigint')
        ]);
        
        $this->setTable('organizations_users');
    }
    
    public function retrieve(int $organization_id, int $user_id): array|false
    {
        $org_model = new OrganizationModel($this->pdo);
        $stmt = $this->prepare(
            'SELECT t1.* ' .
            "FROM {$this->getName()} t1 INNER JOIN {$org_model->getName()} t2 ON t1.organization_id=t2.id " .
            'WHERE t1.user_id=:user AND t1.organization_id=:org AND t2.is_active=1 LIMIT 1'
        );
        $stmt->bindParam(':user', $user_id, \PDO::PARAM_INT);
        $stmt->bindParam(':org', $organization_id, \PDO::PARAM_INT);
        if ($stmt->execute() && $stmt->rowCount() == 1) {
            return $stmt->fetch();
        }
        
        return false;
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $organizations = (new OrganizationModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_user_id FOREIGN KEY (user_id) REFERENCES $users(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_organization_id FOREIGN KEY (organization_id) REFERENCES $organizations(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
        
        $nowdate = \date('Y-m-d H:i:s');
        $this->exec("INSERT INTO $table(created_at,user_id,organization_id) VALUES('$nowdate',1,1)");        
    }
    
    public function insert(array $record): array|false
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record);
    }
}
