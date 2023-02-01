<?php

namespace Indoraptor\Localization;

use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class TextModel extends MultiModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
           (new Column('keyword', 'varchar', 128))->unique(),
            new Column('type', 'int', 4, 0),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setContentColumns([new Column('text', 'varchar', 255)]);
    }
    
    public function setTable(string $name, ?string $collate = null)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \Exception(__CLASS__ . ': Table name must be provided', 1103);
        }
        
        parent::setTable("localization_text_$table", $collate ?? $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    public function retrieve(?string $code = null) : array
    {
        $text = [];
        $codeName = $this->getCodeColumn()->getName();
        if (empty($code)) {
            $stmt = $this->select(
                "p.keyword as keyword, c.$codeName as $codeName, c.text as text",
                ['WHERE' => 'p.is_active=1', 'ORDER BY' => 'p.keyword']);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $text[$row['keyword']][$row[$codeName]] = $row['text'];
            }
        } else {
            $code = \preg_replace('/[^A-Za-z]/', '', $code);
            $condition = [
                'WHERE' => "c.$codeName=:1 AND p.is_active=1",
                'ORDER BY' => 'p.keyword',
                'PARAM' => [':1' => $code]
            ];
            $stmt = $this->select('p.keyword as keyword, c.text as text', $condition);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $text[$row['keyword']] = $row['text'];
            }
        }
        return $text;
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);

        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        
        if (method_exists(TextInitial::class, $table)) {
            TextInitial::$table($this);
        }
        
        $this->setForeignKeyChecks(true);
    }
}
