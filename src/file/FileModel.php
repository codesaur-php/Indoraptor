<?php

namespace Indoraptor\File;

use PDO;

use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class FileModel extends MultiModel
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns(array(
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('file', 'varchar', 255),
            new Column('path', 'varchar', 255, ''),
           (new Column('protection', 'tinyint', 1, 1))->notNull(), // 1 => public; 2 => private
            new Column('category', 'int', 4, 1),
            new Column('size', 'bigint', 8),
            new Column('keyword', 'varchar', 128),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ));
        
        $this->setContentColumns(array(new Column('title', 'varchar', 255)));
        
        $this->setTable('indo_file', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    function __initial()
    {
        parent::__initial();
        
        $table = $this->getName();
        
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
    
    public function getTableRecord(string $table, int $record, int $type, $code = null)
    {
        $files = new FilesModel($this->pdo);
        $files->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        
        $condition = "record=$record AND type=$type AND is_active=1";
        if (isset($code)) {
            $condition .= " AND code='$code'";
        }
        $rows = $files->getRows(array(
            'WHERE' => $condition,
            'ORDER BY' => 'id desc',
            'LIMIT' => 1
        ));
        
        $files_record = end($rows);
        if (isset($files_record['file'])) {
            $data = $this->getById($files_record['file'], $code);

            if ($data) {
                $data['files_id'] = $files_record['id'];
                $data['record'] = $files_record['record'];
                $data['type'] = $files_record['type'] ?? null;
                $data['code'] = $files_record['code'] ?? null;
                $data['rank'] = $files_record['rank'] ?? null;
            }
            
            return $data;
        }
        
        return null;
    }
}
