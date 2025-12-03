<?php

namespace Raptor\Content;

use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

class ReferenceModel extends LocalizedModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('keyword', 'varchar', 128))->unique(),
            new Column('category', 'varchar', 32),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);
        
        $this->setContentColumns([
            new Column('title', 'varchar', 255),
            new Column('content', 'mediumtext')
        ]);
    }
    
    public function setTable(string $name)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \InvalidArgumentException(__CLASS__ . ': Table name must be provided', 1103);
        }
        
        parent::setTable("reference_$table");
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);

        if (\method_exists(ReferenceInitial::class, $table)) {
            ReferenceInitial::$table($this);
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
