<?php

namespace Indoraptor\Contents;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class NewsModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('meta_id', 'bigint', 8),
            new Column('title', 'varchar', 255),
            new Column('code', 'varchar', 6),
            new Column('category', 'varchar', 32, 'general'),
            new Column('type', 'varchar', 32, 'common'),
            new Column('link', 'varchar', 255),
           (new Column('name', 'varchar', 128))->unique(),
            new Column('show_comment', 'tinyint', 1, 1),
            new Column('can_comment', 'tinyint', 1, 1),
            new Column('read_count', 'int', 4, 0),
            new Column('short', 'text'),
            new Column('full', 'mediumtext'),
            new Column('photo', 'varchar', 255),
            new Column('published', 'tinyint', 1, 0),
            new Column('published_date', 'datetime'),
            new Column('published_by', 'bigint', 8),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setTable('indo_news', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);

        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_published_by FOREIGN KEY (published_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE");
        
        $this->setForeignKeyChecks(true);
    }
}
