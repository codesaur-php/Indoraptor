<?php

namespace Raptor\Authentication;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class ForgotModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('forgot_password', 'varchar', 255))->unique(),
            new Column('user_id', 'bigint'),
            new Column('username', 'varchar', 255),
            new Column('first_name', 'varchar', 255),
            new Column('last_name', 'varchar', 255),
            new Column('email', 'varchar', 128),
            new Column('remote_addr', 'varchar', 46),
            new Column('code', 'varchar', 6),
           (new Column('status', 'tinyint'))->default(1),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('updated_at', 'datetime')
        ]);
        
        $this->setTable('forgots');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_user_id FOREIGN KEY (user_id) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
    
    public function insert(array $record): array|false
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record);
    }
    
    public function updateById(int $id, array $record): array|false
    {
        if (!isset($record['updated_at'])) {
            $record['updated_at'] = \date('Y-m-d H:i:s');
        }
        return parent::updateById($id, $record);
    }
}
