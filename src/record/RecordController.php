<?php

namespace Indoraptor\Record;

use Psr\Http\Message\ResponseInterface;

use codesaur\DataObject\Model;
use codesaur\DataObject\MultiModel;

class RecordController extends \Indoraptor\IndoController
{
    public function record(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_record();
    }
    
    public function records(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_records();
    }
    
    public function insert(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_insert();
    }
    
    public function update(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_update();
    }
    
    public function delete(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_delete();
    }
    
    public function internal_record(): ResponseInterface
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
    
    public function internal_records(): ResponseInterface
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
    
    public function internal_insert(): ResponseInterface
    {
        $model = $this->grabModel();
        $record = $this->getParsedBody();
        if (empty($record)) {
            return $this->badRequest();
        }
        
        if ($model instanceof Model) {
            return $this->respond($model->insert($record));
        } elseif (
            $model instanceof MultiModel
            && !empty($record['record'])
            && !empty($record['content'])
        ) {
            return $this->respond($model->insert(
                $record['record'], $record['content']));
        }
        
        throw new \Exception(__CLASS__. ':insert failed!');
    }
    
    public function internal_update(): ResponseInterface
    {
        $model = $this->grabModel();
        $payload = $this->getParsedBody();
        if (!isset($payload['record'])
            || empty($payload['condition'])
        ) {
            $this->badRequest();
        }
        
        if ($model instanceof Model) {
            return $this->respond($model->update(
                        $payload['record'], $payload['condition']));
        } elseif (
            $model instanceof MultiModel
            && isset($payload['content'])
        ) {
            return $this->respond($model->update(
                        $payload['record'], $payload['content'], $payload['condition']));
        }
        
        throw new \Exception(__CLASS__. ':update failed!');
    }
    
    public function internal_delete(): ResponseInterface
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
