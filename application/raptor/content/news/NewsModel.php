<?php

namespace Raptor\Content;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class NewsModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('title', 'varchar', 255),
            new Column('description', 'text'),
            new Column('content', 'mediumtext'),
            new Column('photo', 'varchar', 255),
            new Column('code', 'varchar', 6),
           (new Column('type', 'varchar', 32))->default('common'),
           (new Column('category', 'varchar', 32))->default('general'),
           (new Column('comment', 'tinyint'))->default(1),
           (new Column('read_count', 'bigint'))->default(0),
           (new Column('is_active', 'tinyint'))->default(1),
           (new Column('published', 'tinyint'))->default(0),
            new Column('published_at', 'datetime'),
            new Column('published_by', 'bigint'),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);
        
        $this->setTable('news');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_published_by FOREIGN KEY (published_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
    
    public function insert(array $record): array|false
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record);
    }
}
