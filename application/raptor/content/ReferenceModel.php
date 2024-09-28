<?php

namespace Raptor\Content;

use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class ReferenceModel extends MultiModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
           (new Column('keyword', 'varchar', 128))->unique(),
            new Column('category', 'varchar', 32),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setContentColumns([
            new Column('title', 'varchar', 255),
            new Column('short', 'text'),
            new Column('full', 'mediumtext')
        ]);
    }
    
    public function setTable(string $name, ?string $collate = null)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \Exception(__CLASS__ . ': Table name must be provided', 1103);
        }
        
        parent::setTable("reference_$table", $collate ?? $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        
        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");

        if (\method_exists(ReferenceInitial::class, $table)) {
            ReferenceInitial::$table($this);
        }
        
        $this->setForeignKeyChecks(true);
    }
}
