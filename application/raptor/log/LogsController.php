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
            
            if ($this->getDriverName() == 'pgsql') {
                $query = 
                    'SELECT tablename FROM pg_catalog.pg_tables ' .
                    "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like '%_log'";
            } else {
                $query = 'SHOW TABLES LIKE ' . $this->quote('%_log');
            }
            $log_tables = [];
            $pdostmt = $this->prepare($query);
            if ($pdostmt->execute()) {
                while ($row = $pdostmt->fetch()) {
                    $log_tables[] = \substr(\current($row), 0, -\strlen('_log'));
                }
            }
            $dashboard =  $this->twigDashboard(\dirname(__FILE__) . '/index-list-logs.html', ['log_tables' => $log_tables]);
            $dashboard->set('title', $this->text('log'));
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
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
            $table_name = $params['table'] ?? null;
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table_name);
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
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();

            return false;
        }
    }
    
    public function retrieve()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $params = $this->getQueryParams();
            $table_name = $params['table'] ?? null;
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table_name);
            if (empty($table) || !$this->hasTable("{$table}_log")
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'));
            }
            
            $condition = $this->getParsedBody();
            if (isset($condition['CONTEXT'])) {
                $context = $condition['CONTEXT'];
                unset($condition['CONTEXT']);
            } else {
                $context = null;
            }

            $wheres = [];
            foreach (\is_array($context) ? $context : [] as $field => $value) {
                $isLike = \strpos($value, '*') !== false;
                if ($isLike) {
                    $value = \str_replace('*', '%', $value);
                }
                $quotedValue = $this->quote($value);
                $keys = \explode('.', $field);

                if ($this->getDriverName() == 'pgsql') {
                    $expr = 'context';
                    $lastKey = \array_pop($keys);
                    foreach ($keys as $k) {
                        $expr .= "->'$k'";
                    }
                    $expr .= "->>'$lastKey'";
                } else {
                    $jsonPath = '$';
                    foreach ($keys as $k) {
                        $jsonPath .= ".$k";
                    }
                    $expr = "JSON_UNQUOTE(JSON_EXTRACT(context, '$jsonPath'))";
                }
                
                $wheres[] = $isLike ? "$expr LIKE $quotedValue" : "$expr=$quotedValue";
            }
            $clause = \implode(' AND ', $wheres);
            if (!empty($clause)) {
                $condition['WHERE'] = empty($condition['WHERE'])
                    ? $clause
                    : $condition['WHERE'] . ' AND ' . $clause;
            }

            $logger = new Logger($this->pdo);
            $logger->setTable($table);
            $this->respondJSON($logger->getLogs($condition));
        } catch (\Throwable $e) {
            $this->respondJSON(['error' => $e->getMessage()], $e->getCode());
        }
    }
}
