<?php

namespace Indoraptor\Logger;

use PDO;

use Psr\Log\LogLevel;

class LoggerController extends \Indoraptor\IndoController
{
    public function index()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        if (empty($this->getQueryParam('table'))) {
            return $this->badRequest();
        } elseif (!empty($this->getQueryParam('id'))
                && filter_var($this->getQueryParam('id'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 0))) !== false) {
            $id = (int)$this->getQueryParam('id');
        }
        
        $table = preg_replace('/[^A-Za-z0-9_-]/', '', $this->getQueryParam('table'));
        if ($this->hasTable($table . '_log')) {
            $logger = new LoggerModel($this->pdo);
            $logger->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            if (isset($id)) {
                $data = $logger->getLogById($id);
            } else {
                $limit = $this->getQueryParam('limit');
                $condition = array('ORDER BY' => 'id Desc');
                if ($limit) {
                    $condition['LIMIT'] = $limit;
                }
                $data = array_values($logger->getLogs($condition));
            }

            if (!empty($data)) {
                array_walk_recursive($data, function (&$v, $k) {
                    if (in_array($k, array('jwt', 'token', 'pin', 'password'))) {
                        $v = '*** hidden info ***'; 
                    }
                });
                return $this->respond($data);
            }
        }
        
        return $this->notFound();
    }
    
    public function insert()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        return $this->internal();
    }
    
    public function internal()
    {
        $payload = $this->getParsedBody();
        if (empty($payload['table'])
                || empty($payload['message'])
                || empty($payload['context'])
        ) {
            return $this->badRequest('Invalid payload');
        }
        
        $logger = new LoggerModel($this->pdo);
        $logger->setTable($payload['table'], $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        if (isset($payload['created_by'])) {
            $logger->prepareCreatedBy($payload['created_by']);
        }
        $logger->log($payload['level'] ?? LogLevel::NOTICE, $payload['message'], json_decode($payload['context'], true));
        if ($logger->lastInsertId()) {
            return $this->respond($logger->getLogById($logger->lastInsertId()));
        }
        
        return $this->notFound('Not completed');
    }
    
    public function names()
    {        
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        $pdostmt = $this->prepare('SHOW TABLES LIKE ' . $this->quote('%_log'));
        $pdostmt->execute();
        $names = array();
        while ($rows = $pdostmt->fetch(PDO::FETCH_ASSOC)) {
            $names[] = substr(current($rows), 0, -strlen('_log'));
        }
        if (empty($names)) {
            return $this->notFound();
        }
        
        return $this->respond($names);
    }
    
    public function select()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized('Not allowed');
        }
        
        $payload = $this->getParsedBody();
        if (empty($payload['table'])) {
            return $this->badRequest('Invalid payload');
        }

        $table = preg_replace('/[^A-Za-z0-9_-]/', '', $payload['table']);
        unset($payload['table']);
        
        if ($this->hasTable($table . '_log')) {
            $logger = new LoggerModel($this->pdo);
            $logger->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $data = $logger->getLogs($payload);
        }        
        if (empty($data)) {
            return $this->notFound();
        }
        
        return $this->respond($data);
    }
}
