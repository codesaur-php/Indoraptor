<?php

namespace Indoraptor\File;

use Psr\Http\Message\ResponseInterface;

class FilesController extends \Indoraptor\IndoController
{
    public function index(string $table): ResponseInterface
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
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $record = $model->getRowBy($with_values);
        
        if (empty($record)) {
            return $this->notFound();
        }
        
        return $this->respond($record);
    }
    
    public function internal(string $table): ResponseInterface
    {
        $record = $this->getParsedBody();
        if (empty($record)) {
            return $this->badRequest();
        }
        
        $model = new FilesModel($this->pdo);
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $model->setForeignKeyChecks(false);
        $id = $model->insert($record);
        $model->setForeignKeyChecks(true);
        return $this->respond($id);
    }
    
    public function insert(string $table): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        if (!$this->isExists($table)) {
            return $this->badRequest();
        }
        
        return $this->internal($table);
    }
    
    public function update(string $table): ResponseInterface
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
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $model->setForeignKeyChecks(false);
        $ids = $model->update($payload['record'], $payload['condition']);
        $model->setForeignKeyChecks(true);
        return $this->respond($ids);
    }
    
    public function delete(string $table): ResponseInterface
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
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($model->delete($condition));
    }
    
    public function records(string $table): ResponseInterface
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
    
    private function isExists(string &$table): bool
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        return $this->hasTable("indo_{$table}_files");
    }
}
