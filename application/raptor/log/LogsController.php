<?php

namespace Raptor\Log;

use codesaur\Template\TwigTemplate;

class LogsController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
        
            $logs = [];
            $pdostmt = $this->prepare('SHOW TABLES LIKE ' . $this->quote('%_log'));
            if ($pdostmt->execute()) {
                while ($rows = $pdostmt->fetch()) {
                    $name = \substr(\current($rows), 0, -\strlen('_log'));
                    $logs[$name] = $this->getLogsFrom($name);
                }
            }
            $dashboard =  $this->twigDashboard(
                \dirname(__FILE__) . '/index-list-logs.html',
                [
                    'logs' => $logs,
                    'users' => $this->retrieveUsers()
                ]
            );
            $dashboard->set('title', $this->text('log'));
            $dashboard->render();
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
        }
    }
    
    public function view()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $params = $this->getQueryParams();
            $id = $params['id'] ?? null;
            $table = $params['table'] ?? null;
            if ($id == null || !\is_numeric($id)
                || empty($table) || !$this->hasTable("{$table}_log")
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            } else {
                $id = (int) $id;
            }
            
            $logger = new Logger($this->pdo);
            $logger->setTable($table);
            $logdata = $logger->getLogById($id);
            if (isset($logdata['created_by']) && $logdata['created_by'] !== null) {
                $logdata['created_by'] = $this->retrieveUsers($logdata['created_by'])[$logdata['created_by']];
            }
            \array_walk_recursive($logdata, [$this, 'hideSecret']);
            (new TwigTemplate(
                \dirname(__FILE__) . '/retrieve-log-modal.html',
                [
                    'id' => $id,
                    'table' => $table,
                    'data' => $logdata,
                    'detailed' => $this->text('detailed'),
                    'close' => $this->text('close')
                ]
            ))->render();

            return true;
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();

            return false;
        }
    }
    
    private function getLogsFrom(string $table, int $limit = 1000): array
    {
        try {
            $logger = new Logger($this->pdo);
            $logger->setTable($table);
            $condition = ['ORDER BY' => 'id Desc', 'LIMIT' => $limit];
            $logs = \array_values($logger->getLogs($condition));
            \array_walk_recursive($logs, [$this, 'hideSecret']);
            return $logs;
        } catch (\Throwable $e) {
            $this->errorLog($e);
            return [];
        }
    }
    
    private function hideSecret(&$v, $k)
    {
        $key = \strtoupper($k);
        if (!empty($key)
            && (\in_array($key, ['JWT', 'TOKEN', 'PIN', 'USE_ID', 'REGISTER'])
                || \str_contains($key, 'PASSWORD')
            )
        ) {
            $v = '*** hidden info ***';
        }
    }
}
