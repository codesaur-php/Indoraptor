<?php

namespace Indoraptor\Record;

class RecordController extends \Indoraptor\IndoController
{
    public function record()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_record();
    }
    
    public function records()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_records();
    }
    
    public function insert()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_insert();
    }
    
    public function update()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_update();
    }
    
    public function delete()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_delete();
    }
    
    public function internal_record()
    {
        $with_values = $this->getParsedBody();
        if (empty($with_values)) {
            return $this->badRequest();
        }
        
        $model = $this->grabModel();
        if (method_exists($model, 'getRowBy')) {
            $record = $model->getRowBy($with_values);
        }

        if (empty($record)) {
            return $this->notFound();
        }
        
        return $this->respond($record);
    }
    
    public function internal_records()
    {
        $model = $this->grabModel();
        $condition = $this->getParsedBody();
        if (method_exists($model, 'getRows')) {
            $rows = $model->getRows($condition);
        }

        if (empty($rows)) {
            return $this->notFound();
        }
        
        return $this->respond($rows);
    }
    
    public function internal_insert()
    {
        $model = $this->grabModel();
        $payload = $this->getParsedBody();
        if (empty($payload['record'])
            || !method_exists($model, 'insert')
        ) {
            return $this->badRequest();
        }
        
        if (isset($payload['content'])) {
            $id = $model->insert($payload['record'], $payload['content']);
        } else {
            $id = $model->insert($payload['record']);
        }
        
        return $this->respond($id);
    }
    
    public function internal_update()
    {
        $model = $this->grabModel();
        $payload = $this->getParsedBody();
        if (!isset($payload['record'])
            || empty($payload['condition'])
            || !method_exists($model, 'update')
        ) {
            $this->badRequest();
        }
        
        if (isset($payload['content'])) {
            $id = $model->update($payload['record'], $payload['content'], $payload['condition']);
        } else {
            $id = $model->update($payload['record'], $payload['condition']);
        }
        
        return $this->respond($id);
    }
    
    public function internal_delete()
    {
        $model = $this->grabModel();
        $condition = $this->getParsedBody();
        if (empty($condition)
            || !method_exists($model, 'delete')
        ) {
            return $this->badRequest();
        }
        
        return $this->respond($model->delete($condition));
    }
}
