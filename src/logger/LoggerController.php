<?php

namespace Indoraptor\Logger;

use PDO;

use Psr\Log\LogLevel;

use codesaur\Logger\Logger;

class LoggerController extends \Indoraptor\IndoController
{
    public function index($table, $id = null)
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        if ($this->hasTable($table . '_log')) {
            $logger = new Logger($this->pdo);
            $logger->setTable($table, 'utf8_unicode_ci');
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
    
    public function insert($table)
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        $payload = $this->getParsedBody(true);
        if (empty($payload['message']) || empty($payload['context'])) {
            return $this->badRequest('Invalid payload');
        }
        
        $logger = new Logger($this->pdo, array('rbac_accounts', 'id'));
        $logger->setTable($table, 'utf8_unicode_ci');
        if (isset($payload['created_by'])) {
            $logger->prepareCreatedBy($payload['created_by']);
        }
        $level = $payload['level'] ?? LogLevel::NOTICE;
        $message = $payload['message'] ?? '';
        $context = $payload['context'] ?? array();
        $logger->log($level, $message, $context);

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
    
    public function select($table)
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized('Not allowed');
        }
        
        if ($this->hasTable($table . '_log')) {
            $logger = new Logger($this->pdo);
            $logger->setTable($table, 'utf8_unicode_ci');
            $data = $logger->getLogs($this->getParsedBody() ?? array());
        }        
        if (empty($data)) {
            return $this->notFound();
        }
        
        return $this->respond($data);
    }
}
