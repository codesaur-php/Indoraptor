<?php

namespace Indoraptor\Record;

use PDO;

class RecordController extends \Indoraptor\IndoController
{
    public function internal()
    {
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
    
    public function internal_rows()
    {
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
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_insert();
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
    
    public function update()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_update();
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
    
    public function delete()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal_delete();
    }
    
    public function internal_delete()
    {
        $model = $this->grabModel();
        $payload = $this->getParsedBody();
        if (empty($payload['WHERE'])
            || !method_exists($model, 'delete')
        ) {
            return $this->badRequest();
        }
        
        return $this->respond($model->delete($payload));
    }
    
    public function lookup()
    {
        $payload = $this->getParsedBody();
        if (empty($payload['table'])) {
            return $this->badRequest();
        }
        
        $lookup = new LookupModel($this->pdo);
        $lookup->setTable($payload['table'], $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $rows = $lookup->getRows($payload['condition'] ?? []);
        $records = array();
        foreach ($rows as $row) {
            $records[$row['keyword']] = $row['content'];
        }
        return $this->respond($records);
    }
    
    public function statement()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        $payload = $this->getParsedBody();
        if (!isset($payload['query'])) {
            return $this->badRequest('Invalid payload');
        }
        
        $stmt = $this->pdo->prepare($payload['query']);
        if (isset($payload['bind'])) {
            foreach ($payload['bind'] as $parametr => $values) {
                if (isset($values['var'])) {
                    if (isset($values['length'])) {
                        $stmt->bindParam($parametr, $values['var'], $values['type'] ?? PDO::PARAM_STR, $values['length']);
                    } else {
                        $stmt->bindParam($parametr, $values['var'], $values['type'] ?? PDO::PARAM_STR);
                    }
                }
            }
        }
        $stmt->execute();
        
        $result = array();
        if ($stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['id'])) {
                    $result[$row['id']] = $row;
                } else {
                    $result[] = $row;
                }
            }
        }

        return $this->respond($result);
    }
}
