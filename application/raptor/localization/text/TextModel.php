<?php

namespace Raptor\Localization;

use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

class TextModel extends LocalizedModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('keyword', 'varchar', 128))->unique(),
            new Column('type', 'varchar', 16),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'timestamp'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'timestamp'),
            new Column('updated_by', 'bigint')
        ]);
        
        $this->setContentColumns([new Column('text', 'varchar', 255)]);
    }
    
    public function setTable(string $name)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \InvalidArgumentException(__CLASS__ . ': Table name must be provided', 1103);
        }
        
        parent::setTable("localization_text_$table");
    }
    
    public function retrieve(?string $code = null): array
    {
        $text = [];
        if (empty($code)) {
            $stmt = $this->select(
                "p.keyword as keyword, c.code as code, c.text as text",
                ['WHERE' => 'p.is_active=1', 'ORDER BY' => 'p.keyword']);
            while ($row = $stmt->fetch()) {
                $text[$row['keyword']][$row['code']] = $row['text'];
            }
        } else {
            $condition = [
                'WHERE' => "c.code=:code AND p.is_active=1",
                'ORDER BY' => 'p.keyword',
                'PARAM' => [':code' => $code]
            ];
            $stmt = $this->select('p.keyword as keyword, c.text as text', $condition);
            while ($row = $stmt->fetch()) {
                $text[$row['keyword']] = $row['text'];
            }
        }
        return $text;
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
        
        if (\method_exists(TextInitial::class, $table)) {
            TextInitial::$table($this);
        }
    }
    
    public function insert(array $record, array $content): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record, $content);
    }
    
    public function updateById(int $id, array $record, array $content): array|false
    {
        $record['updated_at'] ??= \date('Y-m-d H:i:s');
        return parent::updateById($id, $record, $content);
    }
}
