<?php

namespace Indoraptor\Contents;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class FilesModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('record_id', 'bigint', 8),
            new Column('file', 'varchar', 255),
            new Column('path', 'varchar', 255, ''),
            new Column('size', 'int', 4),
            new Column('type', 'varchar', 24),
            new Column('mime_content_type', 'varchar', 127),
            new Column('category', 'varchar', 24),
            new Column('keyword', 'varchar', 32),
            new Column('description', 'varchar', 255),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
    }
    
    public function setTable(string $name, ?string $collate = null)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \Exception(__CLASS__ . ': Table name must be provided', 1103);
        }
        
        parent::setTable("{$table}_files", $collate ?? $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }

    public function getRecordName(): string
    {
        return \substr($this->getName(), 0, -(\strlen('_files')));
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        
        $my_name = $this->getName();
        $record_name = $this->getRecordName();
        $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_record_id FOREIGN KEY (record_id) REFERENCES $record_name(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        
        $this->setForeignKeyChecks(true);
    }
}
