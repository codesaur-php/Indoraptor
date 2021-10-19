<?php

namespace Indoraptor\Mail;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class MailerModel extends Model
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);        
        
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
           (new Column('created_by', 'bigint', 20))->constraints('CONSTRAINT mailer_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE'),
            new Column('updated_at', 'datetime'),
           (new Column('updated_by', 'bigint', 20))->constraints('CONSTRAINT mailer_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE')
        ));
        
        $this->setTable('mailer');
    }
}
