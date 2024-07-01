<?php

namespace Indoraptor;

use Psr\Http\Message\ResponseInterface;

use codesaur\DataObject\Model;
use codesaur\DataObject\MultiModel;

class RecordController extends IndoController
{
    public function record(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->record_internal();
    }
    
    public function record_internal(): ResponseInterface
    {
        $with_values = $this->getParsedBody();
        if (empty($with_values)) {
            return $this->badRequest();
        }
        
        $model = $this->grabModel();
        if (\method_exists($model, 'getRowBy')) {
            $record = $model->getRowBy($with_values);
        }

        if (empty($record)) {
            return $this->notFound();
        }
        
        return $this->respond($record);
    }
    
    public function records(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->records_internal();
    }
    
    public function records_internal(): ResponseInterface
    {
        $model = $this->grabModel();
        $condition = $this->getParsedBody();
        if (\method_exists($model, 'getRows')) {
            $rows = $model->getRows($condition);
        }

        if (empty($rows)) {
            return $this->notFound();
        }
        
        return $this->respond($rows);
    }
    
    public function insert(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->insert_internal();
    }
    
    public function insert_internal(): ResponseInterface
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
                $record['record'], $record['content'])
            );
        }
        
        throw new \Exception(__CLASS__. ':insert failed!');
    }
    
    public function update(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->update_internal();
    }
    
    public function update_internal(): ResponseInterface
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
                        $payload['record'], $payload['condition'])
            );
        } elseif (
            $model instanceof MultiModel
            && isset($payload['content'])
        ) {
            return $this->respond($model->update(
                        $payload['record'], $payload['content'], $payload['condition'])
            );
        }
        
        throw new \Exception(__CLASS__. ':update failed!');
    }
    
    public function delete(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->delete_internal();
    }
    
    public function delete_internal(): ResponseInterface
    {
        $model = $this->grabModel();
        $condition = $this->getParsedBody();
        if (empty($condition)
            || !\method_exists($model, 'delete')
        ) {
            return $this->badRequest();
        }
        
        return $this->respond($model->delete($condition));
    }
    
    private function grabModel()
    {
        $params = $this->getQueryParams();
        $cls = $params['model'] ?? null;
        if (empty($cls)) {
            $cls = $this->getParsedBody()['model'] ?? null;
        }
        if (empty($cls)) {
            return null;
        }
        
        $class = \str_replace(' ', '', $cls);
        if (!\class_exists($class)) {
            return null;
        }
        
        return new $class($this->pdo);
    }
    
    public function executeFetchAll(): ResponseInterface
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $payload = $this->getParsedBody();
        if (!isset($payload['query'])) {
            return $this->badRequest('Invalid payload');
        }
        
        $stmt = $this->prepare($payload['query']);
        if (isset($payload['bind'])) {
            foreach ($payload['bind'] as $parametr => $values) {
                if (isset($values['var'])) {
                    if (isset($values['length'])) {
                        $stmt->bindParam($parametr, $values['var'], $values['type'] ?? \PDO::PARAM_STR, $values['length']);
                    } else {
                        $stmt->bindParam($parametr, $values['var'], $values['type'] ?? \PDO::PARAM_STR);
                    }
                }
            }
        }
        $stmt->execute();
        
        return $this->respond($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
