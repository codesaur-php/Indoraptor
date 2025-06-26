<?php

namespace Web\Template;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

class StatsModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('total', 'bigint'),
            new Column('month', 'bigint'),
            new Column('today', 'bigint'),
        ]);
        
        $this->setTable('web_stats');
    }
    
    protected function __initial()
    {
    }
}
