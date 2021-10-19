<?php

namespace Indoraptor\Localization;

use PDO;

use codesaur\Localization\TranslationInitial;

class TranslationController extends \Indoraptor\IndoController
{
    public function index()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
            
        $pdostmt = $this->prepare('SHOW TABLES LIKE ' . $this->quote('translation_%'));
        $pdostmt->execute();

        $likeness = array();
        while ($rows = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
            $likeness[] = current($rows);
        }

        $names = array();
        foreach ($likeness as $name) {
            if (in_array($name . '_content', $likeness)) {
                $names[] = substr($name, strlen('translation_'));
            }
        }
        if (empty($names)) {
            return $this->notFound();
        }

        return $this->respond($names);
    }

    public function retrieve()
    {
        $payload = $this->getParsedBody();
        if (empty($payload['table'])) {
            return $this->badRequest();
        }
        
        if (is_array($payload['table'])) {
            $tables = array_values($payload['table']);
        } else {
            $tables = array($payload['table']);
        }            

        $initial = get_class_methods(TranslationInitial::class);
        $translations = array();
        $code = $payload['code'] ?? null;

        $model = new TranslationModel($this->pdo);
        foreach (array_unique($tables) as $table) {
            if (!in_array("translation_$table", $initial)
                    && !$this->hasTable("translation_$table")
            ) {
                continue;
            }

            $model->setTable($table, 'utf8_unicode_ci');
            $text = $model->retrieve($code);

            if (!empty($text)) {
                $translations[$table] = $text;
            }
        }

        if (!empty($translations)) {
            return $this->respond($translations);
        }
        
        return $this->notFound('Translation not found');
    }
    
    public function findKeyword()
    {
        $payload = $this->getParsedBody();
        if (!isset($payload['keyword'])) {
            return $this->badRequest('Invalid payload');
        }
        
        $show_tables = $this->prepare('SHOW TABLES LIKE ' . $this->quote('translation_%_content'));
        if (!$show_tables->execute()) {
            return $this->notFound('No translation tables found');
        }
        
        while ($name = $show_tables->fetch(PDO::FETCH_NUM)) {
            $table = substr($name[0], 0, strlen($name[0]) - strlen('_content'));
            $select = $this->prepare("SELECT * FROM $table WHERE keyword=:1 LIMIT 1");
            $select->bindParam(':1', $payload['keyword']);
            if (!$select->execute()) {
                continue;
            }
            
            if ($select->rowCount() == 1) {
                $result = array('table' => $table);
                $result += $select->fetch(PDO::FETCH_ASSOC);                
                foreach (array('id', 'type', 'is_active', 'created_by', 'updated_by') as $column) {
                    if (isset($result[$column])) {
                        $result[$column] = (int)$result[$column];
                    }
                }
                return $this->respond($result);
            }
        }
        
        return $this->notFound('Keyword not found');
    }    
    
    public function getInitialMethods()
    {
        return $this->respond(get_class_methods(TranslationInitial::class));
    }
}
