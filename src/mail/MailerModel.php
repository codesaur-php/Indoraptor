<?php

namespace Indoraptor\Mail;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class MailerModel extends Model
{
    function __construct(PDO $pdo, $accountForeignRef = null)
    {
        parent::__construct($pdo);
        
        $created_by = new Column('created_by', 'bigint', 20);
        $updated_by = new Column('updated_by', 'bigint', 20);
        if (!empty($accountForeignRef)) {
            if (is_array($accountForeignRef)) {
                call_user_func_array(array($created_by, 'foreignKey'), $accountForeignRef);
                call_user_func_array(array($updated_by, 'foreignKey'), $accountForeignRef);
            } else {
                $created_by->foreignKey($accountForeignRef, 'id');
                $updated_by->foreignKey($accountForeignRef, 'id');
            }
        }
        
        $this->setColumns(array(
           (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull(),
            new Column('is_smtp', 'tinyint', 1),
            new Column('charset', 'varchar', 6),
            new Column('smtp_auth', 'tinyint', 1),
            new Column('smtp_secure', 'varchar', 6),
            new Column('host', 'varchar', 255),
            new Column('port', 'int', 15),
            new Column('username', 'varchar', 128),
            new Column('password', 'varchar', 255),
            new Column('name', 'varchar', 255),
            new Column('email', 'varchar', 128),
            new Column('created_at', 'datetime'),
            $created_by,
            new Column('updated_at', 'datetime'),
            $updated_by
        ));
        
        $this->setTable('mailer');
    }
}
