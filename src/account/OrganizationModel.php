<?php namespace Indoraptor\Account;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class OrganizationModel extends Model
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns(array(
           (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull(),
            new Column('parent_id', 'bigint', 20),
            new Column('name', 'varchar', 512),
            new Column('logo', 'varchar', 512),
            new Column('home_url', 'varchar', 512),
            new Column('external', 'varchar', 255),
            new Column('alias', 'varchar', 16, 'common'),
            new Column('status', 'tinyint', 1, 1),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
           (new Column('created_by', 'bigint', 20))->constraints('CONSTRAINT organizations_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE'),
            new Column('updated_at', 'datetime'),
           (new Column('updated_by', 'bigint', 20))->constraints('CONSTRAINT organizations_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE')
        ));
        
        $this->setTable('organizations', getenv('INDO_DB_COLLATION', true) ?: 'utf8_unicode_ci');
    }
    
    public function setTable(string $name, $collate = null)
    {
        $this->name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        
        if ($this->hasTable($this->name)) {
            return;
        }
        
        $this->createTable($this->name, $this->columns, $collate);
        $this->exec("ALTER TABLE $this->name ADD CONSTRAINT {$this->name}_fk_parent_id FOREIGN KEY (parent_id) REFERENCES $this->name(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->__initial();
    }
    
    function __initial()
    {
        $table = $this->getName();
        if ($table != 'organizations') {
            return;
        }
        
        $nowdate = date('Y-m-d H:i:s');
        $this->exec("INSERT INTO $table(id,created_at,name,external,alias) VALUES(1,'$nowdate','System',NULL,'system')");
    }
}
