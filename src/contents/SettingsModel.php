<?php

namespace Indoraptor\Contents;

use codesaur\DataObject\Column;
use codesaur\DataObject\MultiModel;

class SettingsModel extends MultiModel
{
    function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint', 8))->auto()->primary()->unique()->notNull(),
            new Column('alias', 'varchar', 16),
            new Column('keywords', 'varchar', 255),
            new Column('email', 'varchar', 70),
            new Column('phone', 'varchar', 70),
            new Column('favico', 'varchar', 255),
            new Column('shortcut_icon', 'varchar', 255),
            new Column('apple_touch_icon', 'varchar', 255),
            new Column('facebook', 'varchar', 255),
            new Column('facebook_widget', 'text'),
            new Column('twitter', 'varchar', 255),
            new Column('twitter_widget', 'text'),
            new Column('youtube', 'varchar', 255),
            new Column('socials', 'text'),
            new Column('options', 'text'),
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
            new Column('contact', 'text'),
            new Column('address', 'varchar', 255),
            new Column('copyright', 'varchar', 255)
        ]);
        
        $this->setTable('indo_settings', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
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
}
