<?php

namespace Indoraptor\File;

class FilesController extends \Indoraptor\IndoController
{
    public function index(string $table)
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
        return $this->respond($model->insert($record));
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
        return $this->respond($model->update(
            $payload['record'],  $payload['condition']));
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
        return $this->respond($model->delete($condition));
    }
    
    function isExists(string &$table): bool
    {
        $table = preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        return $this->hasTable("indo_{$table}_files");
    }
    
    public function records(string $table)
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        } elseif (!$this->isExists($table)) {
            return $this->badRequest();
        }
        
        $condition = $this->getParsedBody();
        $files = new FilesModel($this->pdo);
        $files->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($files->getRows($condition));
    }    
}
