<?php

namespace Indoraptor\File;

use Exception;

class FilesController extends \Indoraptor\IndoController
{
    public function index(string $table)
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $condition = $this->getParsedBody();
        if (empty($condition)
            || !$this->isExists($table)
        ) {
            return $this->badRequest();
        }
        
        $files = new FilesModel($this->pdo);
        $files->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $rows = $files->getRows($condition);
        
        return $this->respond($rows);
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
        
        $model = new FilesModel($this->pdo);
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
        
        $record = $this->getParsedBody();
        if (empty($record)
            || !$this->isExists($table)
        ) {
            return $this->badRequest();
        }
        
        $model = new FilesModel($this->pdo);
        $model->setTable($table);
        $id = $model->insert($record);
        
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
            || empty($payload['condition'])
            || !$this->isExists($table)
        ) {
            return $this->badRequest();
        }
        
        $model = new FilesModel($this->pdo);
        $model->setTable($table);
        $id = $model->update($payload['record'],  $payload['condition']);
        
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
        
        $model = new FilesModel($this->pdo);
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
        return $this->hasTable("indo_{$table}_files");
    }
}
