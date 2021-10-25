<?php

namespace Indoraptor\Record;

class RecordController extends \Indoraptor\IndoController
{
    public function internal()
    {
        $payload = $this->getParsedBody();
        if (empty($payload)) {
            return $this->badRequest();
        }
        
        $model = $this->grabmodel();
        if (method_exists($model, 'getRowBy')) {
            $record = $model->getRowBy($payload);
        }

        if (empty($record)) {
            return $this->notFound();
        }
        
        return $this->respond(array(
            'record' => $record,
            'model'  => get_class($model),
            'table'  => $model->getName()
        ));
    }
    
    public function internal_rows()
    {
        $model = $this->grabmodel();
        $payload = $this->getParsedBody();
        if (method_exists($model, 'getRows')) {
            $rows = $model->getRows($payload);
        }

        if (empty($rows)) {
            return $this->notFound();
        }
        
        return $this->respond(array(
            'rows'  => $rows,
            'model' => get_class($model),
            'table' => $model->getName()
        ));
    }
    
    public function insert()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_insert();
    }
    
    public function internal_insert()
    {
        $model = $this->grabmodel();
        $payload = $this->getParsedBody();
        if (isset($payload['record'])
                && method_exists($model, 'insert')
        ) {
            if (isset($payload['content'])) {
                $id = $model->insert($payload['record'], $payload['content']);
            } else {
                $id = $model->insert($payload['record']);
            }            
        }

        if ($id ?? false) {
            return $this->respond(array(
                'id'    => $id,
                'model' => get_class($model),
                'table' => $model->getName()
            ));
        }
        
        return $this->notFound();
    }
    
    public function update()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_update();
    }
    
    public function internal_update()
    {
        $model = $this->grabmodel();
        $payload = $this->getParsedBody();
        if (isset($payload['record'])
                && !empty($payload['condition'])
                && method_exists($model, 'update')
        ) {
            if (isset($payload['content'])) {
                $id = $model->update($payload['record'], $payload['content'], $payload['condition']);
            } else {
                $id = $model->update($payload['record'], $payload['condition']);
            }            
        }

        if ($id ?? false) {
            return $this->respond(array(
                'id'    => $id,
                'model' => get_class($model),
                'table' => $model->getName()
            ));
        }
        
        return $this->notFound();
    }
    
    public function lookup()
    {
        $payload = $this->getParsedBody();
        if (empty($payload['table'])) {
            return $this->badRequest();
        }
        
        $lookup = new LookupModel($this->pdo);
        $lookup->setTable("lookup_{$payload['table']}",
                getenv('INDO_DB_COLLATION', true) ?: 'utf8_unicode_ci');
        $rows = $lookup->getRows($payload['condition'] ?? []);
        $records = array();
        foreach ($rows as $row) {
            $records[$row['keyword']] = $row['content'];
        }        
        return $this->respond($records);
    }
}
