<?php

namespace Indoraptor\File;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class FileModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('file', 'varchar', 255),
            new Column('path', 'varchar', 255, ''),
            new Column('size', 'int', 4),
            new Column('type', 'varchar', 24),
            new Column('mime_content_type', 'varchar', 32),
            new Column('keyword', 'varchar', 32),
            new Column('description', 'varchar', 255),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setTable('indo_file', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
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
