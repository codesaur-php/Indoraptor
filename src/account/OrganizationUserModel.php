<?php namespace Indoraptor\Account;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class OrganizationUserModel extends Model
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns(array(
           (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull(),
           (new Column('account_id', 'bigint', 20))->notNull()->foreignKey('rbac_accounts', 'id', 'CASCADE'),
           (new Column('organization_id', 'bigint', 20))->notNull()->foreignKey('organizations', 'id', 'CASCADE'),
            new Column('status', 'tinyint', 1, 1),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
           (new Column('created_by', 'bigint', 20))->foreignKey('organizations', 'id'),
            new Column('updated_at', 'datetime'),
           (new Column('updated_by', 'bigint', 20))->foreignKey('organizations', 'id')
        ));
        
        $this->setTable('organization_users', 'utf8_unicode_ci');
    }
    
    function __initial()
    {
        $table = $this->getName();
        if ($table != 'organization_users') {
            return;
        }
        
        $nowdate = date('Y-m-d H:i:s');
        $this->exec("INSERT INTO $table(id,created_at,account_id,organization_id) VALUES(1,'$nowdate',1,1)");
    }
    
    public function retrieve(int $organization_id, int $account_id)
    {
        $stmt = $this->prepare(
                "SELECT * FROM {$this->getName()} WHERE account_id=:1 AND organization_id=:2 AND is_active=1 LIMIT 1");        
        $stmt->bindParam(':1', $account_id, PDO::PARAM_INT);        
        $stmt->bindParam(':2', $organization_id, PDO::PARAM_INT);        
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            foreach ($this->getColumns() as $column) {
                if (isset($record[$column->getName()])) {
                    if ($column->isInt()) {
                        $record[$column->getName()] = (int)$record[$column->getName()];
                    }
                }
            }
            if ($record['status'] == 0) {
                return false;
            }
        } else {
            $record = array(
                'account_id' => $account_id,
                'organization_id' => $organization_id
            );
            
            $id = $this->insert($record);            
            if ($id !== false) {
                $record['id'] = $id;
                $record['status'] = 1;
                $record['is_active'] = 1;
            }
        }
        
        if (isset($record['id'])) {
            return $record;
        } else {
            return false;
        }
    }
}
