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
            
            if ($this->getDriverName() == 'pgsql') {
                $query = 
                    'SELECT tablename FROM pg_catalog.pg_tables ' .
                    "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like '%_log'";
            } else {
                $query = 'SHOW TABLES LIKE ' . $this->quote('%_log');
            }
            
            $pdostmt = $this->prepare($query);
            if ($pdostmt->execute()) {
                while ($rows = $pdostmt->fetch()) {
                    $name = \substr(\current($rows), 0, -\strlen('_log'));
                    $logs[$name] = $this->getLogsFrom($name);
                }
            }
            $dashboard =  $this->twigDashboard(
                \dirname(__FILE__) . '/index-list-logs.html',
                ['logs' => $logs, 'users_detail' => $this->retrieveUsersDetail()]
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
            
            $users_table = (new \Raptor\User\UsersModel($this->pdo))->getName();
            $select_user = "SELECT username,first_name,last_name,email FROM $users_table WHERE id=:id LIMIT 1";
            $pdo_stmt = $this->prepare($select_user);
            if ($pdo_stmt->execute([':id' => $logdata['created_by']])
                && $pdo_stmt->rowCount() > 0
            ) {
                $row = $pdo_stmt->fetch();
                $logdata['created_by_detail'] = "{$row['username']} » {$row['first_name']} {$row['last_name']} ({$row['email']})";
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
            $logs = $logger->getLogs($condition);
            \array_walk_recursive($logs, [self::class, 'hideSecret']);
            return $logs;
        } catch (\Throwable $e) {
            $this->errorLog($e);
            return [];
        }
    }
    
    public static function hideSecret(&$v, $k)
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
