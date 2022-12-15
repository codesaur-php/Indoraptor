<?php

namespace Indoraptor\Record;

class RecordController extends \Indoraptor\IndoController
{
    public function index()
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $payload = $this->getParsedBody();
        if (empty($payload)) {
            return $this->badRequest();
        }
        
        $model = $this->grabModel();
        if (method_exists($model, 'getRowBy')) {
            $record = $model->getRowBy($payload);
        }

        if (empty($record)) {
            return $this->notFound();
        }
        
        return $this->respond($record);
    }
    
    public function rows()
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
         
        $model = $this->grabModel();
        $payload = $this->getParsedBody();
        if (method_exists($model, 'getRows')) {
            $rows = $model->getRows($payload);
        }

        if (empty($rows)) {
            return $this->notFound();
        }
        
        return $this->respond($rows);
    }
    
    public function insert()
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
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
    
    public function update()
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
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
    
    public function delete()
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $model = $this->grabModel();
        $payload = $this->getParsedBody();
        if (empty($payload['WHERE'])
            || !method_exists($model, 'delete')
        ) {
            return $this->badRequest();
        }
        
        return $this->respond($model->delete($payload));
    }
}
