<?php

namespace Indoraptor\Localization;

class TranslationModel extends \codesaur\Localization\TranslationModel
{
    function __initial()
    {
        parent::__initial();
        
        $this->setForeignKeyChecks(false);
        
        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        
        $this->setForeignKeyChecks(true);
    }
}
