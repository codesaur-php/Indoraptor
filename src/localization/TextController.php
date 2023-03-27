<?php

namespace Indoraptor\Localization;

use Psr\Http\Message\ResponseInterface;

class TextController extends \Indoraptor\IndoController
{
    public function create(string $table): ResponseInterface
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $tbl = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        if (empty($tbl)) {
            return $this->badRequest();
        }
        $localization_table = "localization_text_$tbl";
        
        $model = new TextModel($this->pdo);
        $is_exist = $model->query('SHOW TABLES LIKE ' . $this->quote($localization_table))->rowCount() > 0;
        if ($is_exist) {
            return $this->badRequest(__NAMESPACE__ . " table [$tbl] is already exists!");
        }

        $model->setTable($tbl, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $is_created = $model->query('SHOW TABLES LIKE ' . $this->quote($localization_table))->rowCount() > 0;
        if (!$is_created) {
            return $this->badRequest(__NAMESPACE__ . " table [$tbl] creation failed!");
        }
        
        $values = $this->getParsedBody();
        foreach ($values ?? [] as $value) {
            if (isset($value['record']) && isset($value['content'])) {
                $model->insert($value['record'], $value['content']);
            }
        }
        
        return $this->respond([
            'status' => 'success',
            'message' => __NAMESPACE__ . " have created a table [$tbl]!"
        ]);
    }
    
    public function retrieve(): ResponseInterface
    {
        $payload = $this->getParsedBody();
        if (empty($payload['table'])) {
            return $this->badRequest();
        }
        
        if (\is_array($payload['table'])) {
            $tables = \array_values($payload['table']);
        } else {
            $tables = [$payload['table']];
        }
        
        $initial = \get_class_methods(TextInitial::class);
        $texts = [];
        $code = $payload['code'] ?? null;

        foreach (\array_unique($tables) as $table) {
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
            if (!\in_array("localization_text_$table", $initial)
                && !$this->hasTable("localization_text_$table")
            ) {
                continue;
            }

            $model = new TextModel($this->pdo);
            $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $text = $model->retrieve($code);

            if (!empty($text)) {
                $texts[$table] = $text;
            }
        }

        if (!empty($texts)) {
            return $this->respond($texts);
        }
        
        return $this->notFound('Texts not found');
    }
    
    public function names(): ResponseInterface
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        $pdostmt = $this->prepare('SHOW TABLES LIKE ' . $this->quote('localization_text_%'));
        $pdostmt->execute();

        $likeness = [];
        while ($rows = $pdostmt->fetch(\PDO::FETCH_ASSOC)) {
            $likeness[] = \current($rows);
        }

        $names = [];
        foreach ($likeness as $name) {
            if (\in_array($name . '_content', $likeness)) {
                $names[] = \substr($name, \strlen('localization_text_'));
            }
        }
        if (empty($names)) {
            return $this->notFound();
        }

        return $this->respond($names);
    }
    
    public function findKeyword(): ResponseInterface
    {
        $payload = $this->getParsedBody();
        if (empty($payload['keyword'])) {
            return $this->badRequest('Invalid payload');
        }
        
        $show_tables = $this->prepare('SHOW TABLES LIKE ' . $this->quote('localization_text_%_content'));
        if (!$show_tables->execute()) {
            return $this->notFound('No text tables found');
        }
        
        while ($name = $show_tables->fetch(\PDO::FETCH_NUM)) {
            $table = \substr($name[0], 0, \strlen($name[0]) - \strlen('_content'));
            $select = $this->prepare("SELECT * FROM $table WHERE keyword=:1 LIMIT 1");
            $select->bindParam(':1', $payload['keyword']);
            if (!$select->execute()) {
                continue;
            }
            
            if ($select->rowCount() == 1) {
                $result = ['table' => $table];
                $result += $select->fetch(\PDO::FETCH_ASSOC);
                foreach (['id', 'is_active', 'created_by', 'updated_by'] as $column) {
                    if (isset($result[$column])) {
                        $result[$column] = (int) $result[$column];
                    }
                }
                return $this->respond($result);
            }
        }
        
        return $this->notFound('Keyword not found');
    }
    
    public function record(string $table): ResponseInterface
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
        
        $model = new TextModel($this->pdo);
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $record = $model->getRowBy($with_values);
        
        if (empty($record)) {
            return $this->notFound();
        }
        
        return $this->respond($record);
    }
    
    public function insert(string $table): ResponseInterface
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
        
        $model = new TextModel($this->pdo);
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($model->insert($payload['record'], $payload['content']));
    }
    
    public function update(string $table): ResponseInterface
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
        
        $model = new TextModel($this->pdo);
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($model->update($payload['record'], $payload['content'], $payload['condition']));
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
        
        $model = new TextModel($this->pdo);
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        return $this->respond($model->delete($condition));
    }
    
    public function records(string $table): ResponseInterface
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        if (!$this->isExists($table)) {
            return $this->badRequest();
        }
        
        $model = new TextModel($this->pdo);
        $model->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $condition = $this->getParsedBody();
        return $this->respond($model->getRows($condition));
    }
    
    private function isExists(string &$table): bool
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        return $this->hasTable("localization_text_$table");
    }
}
