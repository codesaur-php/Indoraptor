<?php

namespace Indoraptor\Mailer;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class MailerModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('is_smtp', 'tinyint', 1),
            new Column('charset', 'varchar', 6),
            new Column('smtp_auth', 'tinyint', 1),
            new Column('smtp_secure', 'varchar', 6),
            new Column('host', 'varchar', 255),
            new Column('port', 'int', 4),
            new Column('username', 'varchar', 128),
            new Column('password', 'varchar', 255),
            new Column('name', 'varchar', 255),
            new Column('email', 'varchar', 128),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setTable('indo_mailer', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        
        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        
        $this->setForeignKeyChecks(true);
    }
}
