<?php

namespace Indoraptor\Localization;

use PDO;

use codesaur\Localization\LanguageModel;

class LanguageController extends \Indoraptor\IndoController
{
    public function index()
    {
        $app = $this->getQueryParam('app');
        $is_active = $this->getQueryParam('is_active');
        
        $model = new LanguageModel($this->pdo, array('rbac_accounts', 'id'));
        $rows = $model->retrieve($app ?? 'common', $is_active ?? 1);
        if (empty($rows)) {
            return $this->notFound();
        }
        
        return $this->respond($rows);
    }
    
    public function copyMultiModelContent()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        $payload = $this->getParsedBody();
        $from = $payload['from'] ?? false;
        $to = $payload['to'] ?? false;

        if (!$from || !$to ){
            return $this->badRequest('Invalid payload');
        }

        $database = $this->databaseName();
        $stmt = $this->prepare("SHOW TABLES FROM $database LIKE " . $this->quote('%_content'));
        if (!$stmt->execute()) {
            return $this->notFound();
        }
        
        $this->setForeignKeyChecks(false);

        $translated = array();
        while ($rows = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contentTable = current($rows);
            
            $query = $this->query("SHOW COLUMNS FROM $contentTable");
            $columns = $query->fetchAll(PDO::FETCH_ASSOC);
            $id = $parent_id = $code = false;
            $field = $param = array();
            foreach ($columns as $column) {
                $name = $column['Field'];
                if ($name == 'id' && $column['Extra'] == 'auto_increment') {
                    $id = true;
                } elseif ($name == 'parent_id') {
                    $parent_id = true;
                } elseif ($name == 'code') {
                    $code = true;
                } else {
                    $field[] = $name;
                    $param[] = ":$name";
                }
            }
            
            if (!$id || !$parent_id || !$code || empty($field)) {
                continue;
            }
            
            $table = substr($contentTable, 0, strlen($contentTable) - 8);
            if (!$this->hasTable($table)) {
                continue;
            }
            
            $table_query = $this->query("SHOW COLUMNS FROM $table");
            $table_columns = $table_query->fetchAll(PDO::FETCH_ASSOC);
            $update = null;
            $primary = false;
            $updates = array();
            $update_arguments = array();
            $by_account = getenv('CODESAUR_ACCOUNT_ID', true);
            foreach ($table_columns as $column) {
                $name = $column['Field'];
                if ($name == 'id') {
                    $primary = true;
                } elseif ($name == 'updated_at') {
                    $updates[] = 'updated_at=:at';
                } elseif ($name == 'updated_by'
                        && $by_account !== false
                ) {
                    $updates[] = 'updated_by=:by';
                    $update_arguments = array(':by' => $by_account);
                }
            }
            
            if (!$primary) {
                continue;
            }
            
            if (!empty($updates)) {
                $sets = implode(', ', $updates);
                $update = $this->prepare("UPDATE $table SET $sets WHERE id=:id");
            }
            
            $fields = implode(', ', $field);
            $select = $this->prepare("SELECT parent_id, code, $fields FROM $contentTable WHERE code=:1");
            if (!$select->execute(array(':1' => $from))) {
                continue;
            }
            
            $copied = false;
            $params = implode(', ', $param);
            while ($row = $select->fetch(PDO::FETCH_ASSOC)) {                
                $existing = $this->prepare("SELECT id FROM $contentTable WHERE parent_id=:1 AND code=:2");
                $parameters = array(':1' => $row['parent_id'], ':2' => $to);
                if ($existing->execute($parameters) && $existing->rowCount() > 0) {
                    continue;
                }
                
                $insert = $this->prepare("INSERT INTO $contentTable(parent_id, code, $fields) VALUES(:1, :2, $params)");
                foreach ($field as $name) {
                    $parameters[":$name"] = $row[$name];
                }
                if ($insert->execute($parameters)) {
                    $copied = true;
                    
                    if ($update) {
                        $update_arguments[':id'] = $row['parent_id'];
                        $update_arguments[':at'] = date('Y-m-d H:i:s');
                        $update->execute($update_arguments);
                    }
                }
            }
            
            if ($copied) {
                $translated[] = array($table => $contentTable);
            }
        }
        
        $this->setForeignKeyChecks();
        
        if (empty($translated)) {
            return $this->notFound('Nothing changed');
        }
        
        return $this->respond($translated);
    }
}
