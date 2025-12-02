<?php

namespace Raptor\Log;

use codesaur\Template\TwigTemplate;

/**
 * Class LogsController
 * 
 * Indoraptor Framework-ийн Log module-ийн үндсэн Controller.
 * 
 * Логтой холбоотой дараах 3 үндсэн үйлдлийг хариуцна:
 * ───────────────────────────────────────────────────────────────
 * 1) index()   
 *      → Логийн бүх _log хүснэгтийн жагсаалтыг харуулах
 * 
 * 2) view()    
 *      → Нэг логийн дэлгэрэнгүйг modal-аар үзүүлэх
 * 
 * 3) retrieve()
 *      → Логийн өгөгдлийг AJAX-р шүүх, хайх, ORDER BY, LIMIT хийх  
 *         (UI-ийн JS fetch() → JSON response)
 * 
 * Аюулгүй байдлын нөхцөл:
 *      → Хэрэглэгч 'system_logger' эрхтэй байх ёстой.
 * 
 * @package Indoraptor\Log
 */
class LogsController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Логийн бүх хүснэгтийн жагсаалтыг харуулах Dashboard хуудас.
     *
     * Процесс:
     * ───────────────────────────────────────────────────────────────
     * 1) Хэрэглэгч log харах эрхтэй эсэхийг шалгана.
     * 2) MySQL / PostgreSQL аль ч тохиолдолд *_log нэртэй хүснэгтүүдийг олно.
     * 3) Тэдгээрийг dashboard template-д дамжуулж харуулна.
     *
     * @return void
     */
    public function index()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            // DB driver-ийн хувьд 2 өөр лог хандах зарчим:
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
                // Жишээ: dashboard_log → dashboard
                while ($row = $pdostmt->fetch()) {
                    $log_tables[] = \substr(\current($row), 0, -\strlen('_log'));
                }
            }
            $dashboard = $this->twigDashboard(
                __DIR__ . '/index-list-logs.html',
                ['log_tables' => $log_tables]
            );
            $dashboard->set('title', $this->text('log'));
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        }
    }

    /**
     * Нэг логийн бичлэгийг modal-аар харуулах.
     *
     * Query params:
     * ───────────────────────────────────────────────
     * ?id=123
     * ?table=dashboard
     *
     * Процесс:
     * 1) Параметр шалгах (id тоон байх, хүснэгт зөв байх)
     * 2) Logger model → setTable()
     * 3) getLogById(id) → log data
     * 4) retrieve-log-modal.html template-д дамжуулж render хийх
     *
     * @return void
     */
    public function view()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $params = $this->getQueryParams();
            $param_id = $params['id'] ?? null;
            $table_name = $params['table'] ?? null;

            // Аюулгүй байдлын үүднээс хүснэгтийн нэрийг цэвэрлэнэ
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table_name);

            if ($param_id === null || !\is_numeric($param_id)
                || empty($table) || !$this->hasTable("{$table}_log")) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = (int) $param_id;

            $logger = new Logger($this->pdo);
            $logger->setTable($table);
            $log = $logger->getLogById($id);

            (new TwigTemplate(
                __DIR__ . '/retrieve-log-modal.html',
                [
                    'id' => (int) $id,
                    'table' => $table,
                    'logdata' => $log,
                    'close' => $this->text('close'),
                    'log_caption' => $this->text('log')
                ]
            ))->render();

        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        }
    }

    /**
     * Логийн ашиглалтын өгөгдлийг AJAX-р авах API.
     *
     * Энэ API нь UI дээрх:
     *   - хайлт
     *   - шүүлтүүр (context.action, context.alias ...)
     *   - ORDER BY
     *   - LIMIT
     * бүгдийг хариуцдаг.
     *
     * Request format:
     * ───────────────────────────────────────────────
     * POST /dashboard/logs/retrieve?table=dashboard
     * Body (JSON):
     * {
     *      "ORDER BY": "id DESC",
     *      "LIMIT": 100,
     *      "CONTEXT": {
     *          "action": "rbac-*",
     *          "alias": "system"
     *      }
     * }
     *
     * @return void
     */
    public function retrieve()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $params = $this->getQueryParams();
            $table_name = $params['table'] ?? null;
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table_name);
            if (empty($table) || !$this->hasTable("{$table}_log")) {
                throw new \InvalidArgumentException($this->text('invalid-request'));
            }

            // Filter болон Query нөхцөл
            $condition = $this->getParsedBody();
            $context = $condition['CONTEXT'] ?? null;
            unset($condition['CONTEXT']);

            // JSON талбарын хайлтыг MySQL / PostgreSQL-д тааруулан хийх
            $wheres = [];
            foreach (\is_array($context) ? $context : [] as $field => $value) {
                $isLike = \strpos($value, '*') !== false;
                if ($isLike) {
                    $value = \str_replace('*', '%', $value);
                }
                $quotedValue = $this->quote($value);

                $keys = \explode('.', $field);

                if ($this->getDriverName() == 'pgsql') {
                    // JSONB → a->'b'->>'c'
                    $expr = '(context::jsonb)';
                    $lastKey = \array_pop($keys);
                    foreach ($keys as $k) {
                        $expr .= "->'$k'";
                    }
                    $expr .= "->>'$lastKey'";
                } else {
                    // MySQL JSON_EXTRACT
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
        } catch (\Throwable $err) {
            $this->respondJSON(['error' => $err->getMessage()], $err->getCode());
        }
    }
}
