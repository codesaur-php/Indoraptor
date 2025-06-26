<?php

namespace Raptor\User;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class UsersModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('username', 'varchar', 143))->unique(),
           (new Column('password', 'varchar', 255))->default(''),
            new Column('first_name', 'varchar', 128),
            new Column('last_name', 'varchar', 128),
            new Column('phone', 'varchar', 128),
           (new Column('email', 'varchar', 143))->unique(),
            new Column('photo', 'varchar', 255),
            new Column('code', 'varchar', 255),
           (new Column('status', 'tinyint'))->default(1),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);
        
        $this->setTable('users');
    }

    // <editor-fold defaultstate="collapsed" desc="initial">
    protected function __initial()
    {
        $table = $this->getName();
        $now_date = \date('Y-m-d H:i:s');
        $password = $this->quote(\password_hash('password', \PASSWORD_BCRYPT));
        $query =
            "INSERT INTO $table(created_at,username,password,first_name,last_name,email) " .
            "VALUES('$now_date','admin',$password,'Admin','System','admin@example.com')";
        $this->exec($query);
    }
    // </editor-fold>
    
    public function insert(array $record): array|false
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record);
    }
}
