<?php

namespace Indoraptor\Logger;

class LoggerModel extends \codesaur\Logger\Logger
{
    function __initial()
    {
        parent::__initial();
        
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE {$this->getName()} ADD CONSTRAINT {$this->getName()}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
    
    public function setTable(string $name, $collate = null)
    {
        parent::setTable("indo_$name", $collate);
    }
}
