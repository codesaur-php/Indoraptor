<?php

namespace Indoraptor\Localization;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class LanguageModel extends Model
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->setColumns(array(
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('code', 'varchar', 6),
            new Column('full', 'varchar', 128),
            new Column('description', 'text'),
            new Column('is_default', 'tinyint', 1, 0),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ));
        
        $this->setTable('localization_language', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    public function retrieve(int $is_active = 1)
    {
        $languages = array();
        $condition = array(
            'WHERE' => "is_active=$is_active",
            'ORDER BY' => 'is_default Desc'
        );
        $stmt = $this->select('*', $condition);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $languages[$row['code']] = $row['full'];
        }        
        return $languages;
    }

    public function getByCode(string $code, int $is_active = 1)
    {
        $codeCleaned = preg_replace('/[^A-Za-z0-9_-]/', '', $code);
        return reset($this->getRows(array(
            'WHERE' => 'code=' . $this->quote($codeCleaned) . " AND is_active=$is_active",
            'ORDER BY' => 'is_default Desc'
        ))) ?: null;
    }

    function __initial()
    {
        parent::__initial();
        
        $table = $this->getName();
        
        $this->setForeignKeyChecks(false);
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
        
        $nowdate = date('Y-m-d H:i:s');
        $query =  "INSERT INTO $table(created_at,code,full,is_default)"
            . " VALUES('$nowdate','mn','Монгол',1),('$nowdate','en','English',0)";
        $this->exec($query);
    }
}
