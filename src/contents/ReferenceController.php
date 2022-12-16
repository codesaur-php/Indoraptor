<?php

namespace Indoraptor\Contents;

use Exception;

class ReferenceController extends \Indoraptor\IndoController
{
    public function index(string $table)
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $condition = $this->getParsedBody();
        if (empty($condition)) {
            return $this->badRequest();
        }
        
        $records = array();
        $initial = get_class_methods(ReferenceInitial::class);
        $tbl = preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        if (in_array("reference_$tbl", $initial)
            || $this->hasTable("reference_$tbl")
        ) {
            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($tbl, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $rows = $reference->getRows($condition);
            foreach ($rows as $row) {
                $records[$row['keyword']] = $row['content'];
            }
        }
        
        return $this->respond($records);
    }
    
    public function record(string $table)
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $with_values = $this->getParsedBody();
        if (empty($with_values)
            || !$this->isExists($table)
        ) {
            return $this->badRequest();
        }
        
        $model = new ReferenceModel($this->pdo);
        $model->setTable($table);
        $record = $model->getRowBy($with_values);
        
        if (empty($record)) {
            return $this->notFound();
        }
        
        return $this->respond($record);
    }
    
    public function insert(string $table)
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        $payload = $this->getParsedBody();
        if (empty($payload['record'])
            || empty($payload['content'])
            || !$this->isExists($table)
        ) {
            return $this->badRequest();
        }
        
        $model = new ReferenceModel($this->pdo);
        $model->setTable($table);
        $id = $model->insert($payload['record'], $payload['content']);
        
        if (empty($id)) {
            throw new Exception(__CLASS__. ':' . __FUNCTION__ . ' failed!');
        }        
        
        return $this->respond($id);
    }
    
    public function update(string $table)
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        $payload = $this->getParsedBody();
        if (empty($payload['record'])
            || empty($payload['content'])
            || empty($payload['condition'])
            || !$this->isExists($table)
        ) {
            return $this->badRequest();
        }
        
        $model = new ReferenceModel($this->pdo);
        $model->setTable($table);
        $id = $model->update($payload['record'], $payload['content'], $payload['condition']);
        
        if (empty($id)) {
            throw new Exception(__CLASS__. ':' . __FUNCTION__ . ' failed!');
        }        
        
        return $this->respond($id);
    }
    
    public function delete(string $table)
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        $condition = $this->getParsedBody();
        if (empty($condition)
            || !$this->isExists($table)
        ) {
            return $this->badRequest();
        }
        
        $model = new ReferenceModel($this->pdo);
        $model->setTable($table);
        $id = $model->delete($condition);
        
        if (empty($id)) {
            throw new Exception(__CLASS__. ':' . __FUNCTION__ . ' failed!');
        }        
        
        return $this->respond($id);
    }
    
    function isExists(string &$table): bool
    {
        $table = preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        return $this->hasTable("reference_$table");
    }
}
