<?php

namespace Indoraptor\Record;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class FilesModel extends Model
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns(array(
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
           (new Column('record', 'bigint', 8))->notNull(),
           (new Column('file', 'bigint', 8))->notNull(),
            new Column('type', 'int', 5),
            new Column('code', 'varchar', 6, ''),
            new Column('rank', 'int', 4, 10),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ));
        
        $this->setTable('file');
    }
    
    function __initial()
    {
        parent::__initial();
        
        $my_name = $this->getName();
        $record_table_name = $this->getNameClean();
        
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_file FOREIGN KEY (file) REFERENCES file(id) ON DELETE CASCADE ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_record FOREIGN KEY (record) REFERENCES $record_table_name(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
    
    public function setTable(string $name) : bool
    {
        return parent::setTable(preg_replace('/[^A-Za-z0-9_-]/', '', $name) . '_files');
    }

    public function getNameClean()
    {
        return substr($this->getTable(), 0, -(strlen('_files')));
    }
}
