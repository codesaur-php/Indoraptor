<?php

namespace Raptor\Content;

use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

class SettingsModel extends LocalizedModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('email', 'varchar', 70),
            new Column('phone', 'varchar', 70),
            new Column('favico', 'varchar', 255),
            new Column('apple_touch_icon', 'varchar', 255),
            new Column('config', 'text'),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
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
        
        $this->setTable('raptor_settings');
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
    
    public function insert(array $record, array $content): array|false
    {
        if (!isset($record['created_at'])) {
            $record['created_at'] = \date('Y-m-d H:i:s');
        }
        return parent::insert($record, $content);
    }
    
    public function updateById(int $id, array $record, array $content): array|false
    {
        if (!isset($record['updated_at'])) {
            $record['updated_at'] = \date('Y-m-d H:i:s');
        }
        return parent::updateById($id, $record, $content);
    }

    public function retrieve(): array
    {
        return $this->getRowBy(['p.is_active' => 1]) ?? [];
    }
}
