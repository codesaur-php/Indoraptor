<?php

namespace Indoraptor\Contents;

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
        
        $name = $this->tableName($table);
        $with_values = $this->getParsedBody();
        if (empty($with_values)
            || !$this->isExists($name)
        ) {
            return $this->badRequest();
        }
        
        $model = new FilesModel($this->pdo);
        $model->setTable($name, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $record = $model->getRowBy($with_values);
        
        if (empty($record)) {
            return $this->notFound();
        }
        
        return $this->respond($record);
    }
    
    public function insert(string $table): ResponseInterface
    {
        $name = $this->tableName($table);
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        } elseif (!$this->isExists($name)) {
            return $this->badRequest();
        }
        
        return $this->insert_internal($name);
    }
    
    public function insert_internal(string $table): ResponseInterface
    {
        $record = $this->getParsedBody();
        if (empty($record)) {
            return $this->badRequest();
        }
        
        $model = new FilesModel($this->pdo);
        $model->setTable($this->tableName($table), $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($model->insert($record));
    }
    
    public function update(string $table): ResponseInterface
    {
        $name = $this->tableName($table);
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        } elseif (!$this->isExists($name)) {
            return $this->badRequest();
        }
        
        return $this->update_internal($name);
    }
    
    public function update_internal(string $table): ResponseInterface
    {
        $payload = $this->getParsedBody();
        if (empty($payload['record'])
            || empty($payload['condition'])
        ) {
            return $this->badRequest();
        }
        
        $model = new FilesModel($this->pdo);
        $model->setTable($this->tableName($table), $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($model->update($payload['record'], $payload['condition']));
    }
    
    public function delete(string $table): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        $name = $this->tableName($table);
        $condition = $this->getParsedBody();
        if (empty($condition)
            || !$this->isExists($name)
        ) {
            return $this->badRequest();
        }
        
        $model = new FilesModel($this->pdo);
        $model->setTable($name, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($model->delete($condition));
    }
    
    public function records(string $table): ResponseInterface
    {
        $name = $this->tableName($table);
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        } elseif (!$this->isExists($name)) {
            return $this->badRequest();
        }
        
        $condition = $this->getParsedBody();
        $files = new FilesModel($this->pdo);
        $files->setTable($name, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($files->getRows($condition));
    }
    
    private function isExists(string &$table): bool
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        return $this->hasTable("{$table}_files");
    }
    
    private function tableName($name): string
    {
        $suffix = '_files';
        if (\str_ends_with($name, $suffix)) {
            $name = \substr($name, 0, -\strlen($suffix));
        }
        return $name;
    }
}
