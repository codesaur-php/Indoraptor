<?php

namespace Indoraptor;

use codesaur\Http\Application\Application;

class IndoApplication extends Application
{
    public function __construct()
    {
        parent::__construct();
        
        $this->use(new PDOConnectMiddleware());
        
        $this->GET('/record', [RecordController::class, 'record']);
        $this->INTERNAL('/record', [RecordController::class, 'record_internal']);
        
        $this->GET('/records', [RecordController::class, 'records']);
        $this->INTERNAL('/records', [RecordController::class, 'records_internal']);
        
        $this->POST('/record', [RecordController::class, 'insert']);
        $this->INTERNAL('/record/insert', [RecordController::class, 'insert_internal']);
        
        $this->PUT('/record', [RecordController::class, 'update']);
        $this->INTERNAL('/record/update', [RecordController::class, 'update_internal']);
        
        $this->DELETE('/record', [RecordController::class, 'delete']);
        $this->INTERNAL('/record/delete', [RecordController::class, 'delete_internal']);
        
        $this->INTERNAL('/execute/fetch/all', [RecordController::class, 'executeFetchAll']);        
        
        $this->GET('/', function()
        {
            echo '{"application":"codesaur/indoraptor: '
                . \Composer\InstalledVersions::getPrettyVersion('codesaur/indoraptor') . '"}';
        });
    }
}
