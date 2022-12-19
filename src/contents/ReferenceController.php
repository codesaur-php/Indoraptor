<?php

namespace Indoraptor\Contents;

class ReferenceController extends \Indoraptor\IndoController
{
    public function index(string $table)
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $records = array();
        $condition = $this->getParsedBody();
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
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($model->insert($payload['record'], $payload['content']));
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
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $this->respond($model->update($payload['record'], $payload['content'], $payload['condition']));
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
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($model->delete($condition));
    }
    
    public function records(string $table)
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $tbl = preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        $initial = get_class_methods(ReferenceInitial::class);
        if (!in_array("reference_$tbl", $initial)
            && !$this->hasTable("reference_$tbl")
        ) {
            return $this->notFound('Invalid refence table name!');
        }
        
        $model = new ReferenceModel($this->pdo);
        $model->setTable($tbl, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $condition = $this->getParsedBody();
        return $this->respond($model->getRows($condition));
    }
    
    function isExists(string &$table): bool
    {
        $table = preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        return $this->hasTable("reference_$table");
    }
}
