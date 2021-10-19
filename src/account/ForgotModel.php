<?php

namespace Indoraptor\Account;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class ForgotModel extends Model
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns(array(
           (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull(),
           (new Column('account', 'bigint', 20))->constraints('CONSTRAINT forgot_fk_account FOREIGN KEY (account) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE'),
            new Column('use_id', 'varchar', 256),
            new Column('username', 'varchar', 256),
            new Column('first_name', 'varchar', 256),
            new Column('last_name', 'varchar', 256),
            new Column('email', 'varchar', 128),
            new Column('code', 'varchar', 6),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime')
        ));
        
        $this->setTable('forgot', getenv('INDO_DB_COLLATION', true) ?: 'utf8_unicode_ci');
    }
}
