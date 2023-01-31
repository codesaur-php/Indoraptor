<?php

namespace Indoraptor\Logger;

class LoggerModel extends \codesaur\Logger\Logger
{
    public function setTable(string $name, ?string $collate = null)
    {
        $table = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \Exception(__CLASS__ . ': Logger table name must be provided', 1103);
        }
        
        parent::setTable("indo_$table", $collate ?? $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE {$this->getName()} ADD CONSTRAINT {$this->getName()}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
}
