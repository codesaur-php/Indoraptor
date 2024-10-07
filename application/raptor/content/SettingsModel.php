<?php

namespace Raptor\Content;

use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class SettingsModel extends MultiModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('keywords', 'varchar', 255),
            new Column('email', 'varchar', 70),
            new Column('phone', 'varchar', 70),
            new Column('favico', 'varchar', 255),
            new Column('shortcut_icon', 'varchar', 255),
            new Column('apple_touch_icon', 'varchar', 255),
            new Column('config', 'text'),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint', 8),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint', 8)
        ]);
        
        $this->setContentColumns([
            new Column('title', 'varchar', 70),
            new Column('logo', 'varchar', 255),
            new Column('description', 'varchar', 255),
            new Column('urgent', 'text'),
            new Column('contact', 'text'),
            new Column('address', 'text'),
            new Column('copyright', 'varchar', 255)
        ]);
        
        $this->setTable('raptor_settings', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);

        $table = $this->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");

        $this->setForeignKeyChecks(true);
    }
}
