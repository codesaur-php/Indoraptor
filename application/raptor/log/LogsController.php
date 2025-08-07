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
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            } else {
                $id = (int) $id;
            }
            
            $logger = new Logger($this->pdo);
            $logger->setTable($table);
            $log = $logger->getLogById($id);
            (new TwigTemplate(
                \dirname(__FILE__) . '/retrieve-log-modal.html',
                [
                    'id' => $id,
                    'table' => $table,
                    'logdata' => $log,
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
    
    public function retrieve(string $table)
    {
        try {
            if (!$this->hasTable("{$table}_log")) {
                throw new \InvalidArgumentException($this->text('invalid-request'));
            }
            $logger = new Logger($this->pdo);
            $logger->setTable($table);
            $condition = $this->getParsedBody();
            if (isset($condition['CONTEXT'])) {
                $context = $condition['CONTEXT'];
                unset($condition['CONTEXT']);
                $wheres = [];
                foreach (\is_array($context) ? $context : [] as $field => $value) {
                    if ($this->getDriverName() == 'pgsql') {
                        $wheres[] = "context::json->>'$field'=" . $this->quote($value);
                    } else {
                        $wheres[] = "JSON_EXTRACT(context, '$.$field')=" . $this->quote($value);
                    }
                }
                $clause = \implode(' AND ', $wheres);
                if (!empty($clause)) {
                    if (empty($condition['WHERE'])) {
                        $condition['WHERE'] = '';
                    } else {
                        $condition['WHERE'] .= ' AND ';
                    }
                    $condition['WHERE'] .= $clause;
                }
            }
            $this->respondJSON($logger->getLogs($condition));
        } catch (\Throwable $e) {
            $this->respondJSON(['error' => $e->getMessage()], $e->getCode());
        }
    }
}
