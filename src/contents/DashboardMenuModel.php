<?php

namespace Indoraptor\Contents;

use PDO;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class DashboardMenuModel extends Model
{
    function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        
        $this->setColumns(array(
           (new Column('id', 'bigint', 20))->auto()->primary()->unique()->notNull(),
            new Column('parent_id', 'int', 20, 0),
            new Column('feather', 'varchar', 6),
            new Column('title', 'varchar', 6),
            new Column('href', 'varchar', 255),
            new Column('position', 'int', 8, 100),
            new Column('is_active', 'tinyint', 1, 1),
            new Column('created_at', 'datetime'),
           (new Column('created_by', 'bigint', 20))->constraints('CONSTRAINT dashboard_menu_fk_created_by FOREIGN KEY (created_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE'),
            new Column('updated_at', 'datetime'),
           (new Column('updated_by', 'bigint', 20))->constraints('CONSTRAINT dashboard_menu_fk_updated_by FOREIGN KEY (updated_by) REFERENCES rbac_accounts(id) ON DELETE SET NULL ON UPDATE CASCADE')
        ));
        
        $this->setTable('dashboard_menu');
    }
}
