<?php

namespace Raptor\Localization;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class LanguageModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('code', 'varchar', 6),
            new Column('title', 'varchar', 128),
            new Column('description', 'varchar', 255),
           (new Column('is_default', 'tinyint'))->default(0),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);
        
        $this->setTable('localization_language');
    }
    
    public function retrieve(int $is_active = 1)
    {
        $languages = [];
        $condition = [
            'WHERE' => "is_active=$is_active",
            'ORDER BY' => 'is_default Desc'
        ];
        $stmt = $this->selectStatement($this->getName(), '*', $condition);
        while ($row = $stmt->fetch()) {
            $languages[$row['code']] = $row['title'];
        }
        return $languages;
    }

    public function getByCode(string $code, int $is_active = 1)
    {
        return $this->getRowWhere([
            'code' => $code,
            'is_active' => $is_active
        ]);
    }

    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
        
        $nowdate = \date('Y-m-d H:i:s');
        $query =
            "INSERT INTO $table(created_at,code,title,is_default) " .
            "VALUES('$nowdate','mn','Монгол',1),('$nowdate','en','English',0)";
        $this->exec($query);
    }
    
    public function insert(array $record): array|false
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record);
    }
    
    public function updateById(int $id, array $record): array|false
    {
        if (!isset($record['updated_at'])) {
            $record['updated_at'] = \date('Y-m-d H:i:s');
        }
        return parent::updateById($id, $record);
    }
}
