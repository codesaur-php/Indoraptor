<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

use Raptor\Log\Logger;
use Raptor\Log\LogsController;

class ReferencesController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        if ($this->getDriverName() == 'pgsql') {
            $query = 
                'SELECT tablename FROM pg_catalog.pg_tables ' .
                "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like 'reference_%'";
        } else {
            $query = 'SHOW TABLES LIKE ' . $this->quote('reference_%');
        }

        $reference_likes = $this->query($query)->fetchAll();
        $names = [];
        foreach ($reference_likes as $name) {
            $names[] = \reset($name);
        }
        $references = [];
        foreach ($names as $name) {
            if (\in_array($name . '_content', $names)) {
                $references[] = \substr($name, \strlen('reference_'));
            }
        }
        $initials = \get_class_methods(ReferenceInitial::class);
        foreach ($initials as $value) {
            $initial = \substr($value, \strlen('reference_'));
            if (!empty($initial) && !\in_array($initial, $references)) {
                $references[] = $initial;
            }
        }
        $tables = ['templates' => []];
        foreach ($references as $reference) {
            if (!\in_array($reference, \array_keys($tables))) {
                $tables[$reference] = [];
            }
        }
        
        foreach (\array_keys($tables) as $table) {
            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table);
            $tables[$table] = $reference->getRows();
        }
        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/references-index.html', ['tables' => $tables]);
        $dashboard->set('title', $this->text('reference-tables'));
        $dashboard->render();
        
        $this->indolog('content', LogLevel::NOTICE, 'Лавлах хүснэгтүүдийн жагсаалтыг нээж үзэж байна', ['model' => ReferenceModel::class]);
    }
    
    public function insert(string $table)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            $context = ['model' => ReferenceModel::class, 'table' => $table];
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $record = [];
                $content = [];
                $payload = $this->getParsedBody();
                foreach ($payload as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                        }
                    } else {
                        $record[$index] = $value;
                    }
                }
                $context['payload'] = $payload;
                
                if (empty($payload['keyword'])
                    || empty($payload['category'])
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-values'), 400);
                }
                
                $initials = \get_class_methods(ReferenceInitial::class);
                if (!$this->hasTable("reference_$table")
                    && !\in_array("reference_$table", $initials)
                ) {
                    throw new \Exception("Table reference_$table not found!", 404);
                }
                
                $reference = new ReferenceModel($this->pdo);
                $reference->setTable($table);
                $record['created_by'] = $this->getUserId();
                $insert = $reference->insert($record, $content);
                if (!isset($insert['id'])) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $id = $insert['id'];
                $context['id'] = $id;
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Шинээр [{$payload['keyword']}] түлхүүртэй лавлах мэдээллийг [$table] хүснэгт дээр үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/reference-insert.html', ['table' => $table]);
                $dashboard->set('title', $this->text('add-record') . ' | ' . \ucfirst($table));
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = "Шинэ лавлах мэдээллийг [$table] хүснэгт дээр үйлдлийг эхлүүллээ";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = "Шинэ лавлах мэдээллийг [$table] хүснэгтэд үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function view(string $table, int $id)
    {
        try {
            $context = ['id' => $id, 'model' => ReferenceModel::class, 'table' => $table];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            } elseif (!$this->hasTable("reference_$table")) {
                throw new \Exception("Table reference_$table not found", 404);
            }

            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table);
            $record = $reference->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $context['record'] = $record;
            
            $logger = new Logger($this->pdo);
            $logger->setTable('content');
            $condition = ['ORDER BY' => 'id Desc'];
            if ($this->getDriverName() == 'pgsql') {
                $condition['WHERE'] =
                    '(context::json->>\'id\'=' . $this->quote($id) .
                    ' AND context::json->>\'model\'=' . $this->quote($context['model']) .
                    ' AND context::json->>\'table\'=' . $this->quote($context['table']);
            } else {
                $condition['WHERE'] =
                    'JSON_EXTRACT(context, "$.id")=' . $this->quote($id) .
                    ' AND JSON_EXTRACT(context, "$.model")=' . $this->quote($context['model']) .
                    ' AND JSON_EXTRACT(context, "$.table")=' . $this->quote($context['table']);
            }
            $logs = $logger->getLogs($condition);
            \array_walk_recursive($logs, [LogsController::class, 'hideSecret']);

            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/reference-view.html',
                [
                    'table' => $table, 'record' => $record,
                    'logs' => $logs, 'users_detail' => $this->retrieveUsersDetail()
                ]
            );
            $dashboard->set('title', $this->text('view-record') . ' | ' . \ucfirst($table));
            $dashboard->render();

            $level = LogLevel::NOTICE;
            $message = "$table хүснэгтийн $id дугаартай [{$record['keyword']}] түлхүүртэй лавлах мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = "$table хүснэгтээс $id дугаартай лавлах мэдээллийг нээж үзэх үед алдаа гарч зогслоо";
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function update(string $table, int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => ReferenceModel::class, 'table' => $table];
            
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            } elseif (!$this->hasTable("reference_$table")) {
                throw new \Exception("Table reference_$table not found", 404);
            }
            
            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table);
            $current = $reference->getById($id);
            if (empty($current)) {
                throw new \Exception($this->text('no-record-selected'), 400);
            }
            $context['record'] = $current;
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                $context['payload'] = $payload;
                if (empty($payload)) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }

                $record = [];
                $content = [];
                $context['updates'] = [];
                foreach ($payload as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                            if ($current['localized'][$index][$key] != $value) {
                                $context['updates'][] = "{$index}_{$key}";
                            }
                        }
                    } else {
                        $record[$index] = $value;
                        if ($current[$index] != $value) {
                            $context['updates'][] = $index;
                        }
                    }
                }
                if (empty($context['updates'])) {
                    throw new \InvalidArgumentException('No update!');
                }
                
                $record['updated_at'] = \date('Y-m-d H:i:s');
                $record['updated_by'] = $this->getUserId();
                $updated = $reference->updateById($id, $record, $content);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "$table хүснэгтийн $id дугаартай [{$record['keyword']}] түлхүүртэй лавлах мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $logger = new Logger($this->pdo);
                $logger->setTable('content');
                $condition = ['ORDER BY' => 'id Desc'];
                if ($this->getDriverName() == 'pgsql') {
                    $condition['WHERE'] =
                        '(context::json->>\'id\')::bigint=' . $id .
                        ' AND context::json->>\'model\'=' . $this->quote($context['model']) .
                        ' AND context::json->>\'table\'=' . $this->quote($context['table']);
                } else {
                    $condition['WHERE'] =
                        'JSON_EXTRACT(context, "$.id")=' . $id .
                        ' AND JSON_EXTRACT(context, "$.model")=' . $this->quote($context['model']) .
                        ' AND JSON_EXTRACT(context, "$.table")=' . $this->quote($context['table']);
                }
                $logs = $logger->getLogs($condition);
                \array_walk_recursive($logs, [LogsController::class, 'hideSecret']);
                
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/reference-update.html',
                    [
                        'table' => $table, 'record' => $current,
                        'logs' => $logs, 'users_detail' => $this->retrieveUsersDetail()
                    ]
                );
                $dashboard->set('title', $this->text('edit-record') . ' | ' . \ucfirst($table));
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = "$table хүснэгтийн $id дугаартай [{$current['keyword']}] түлхүүртэй лавлах мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = "$table хүснэгтийн $id дугаартай лавлах мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => ReferenceModel::class];
            
            if (!$this->isUserCan('system_content_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            $context['payload'] = $payload;
            if (!isset($payload['id'])
                || empty($payload['table'])
                || !isset($payload['keyword'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            } elseif (!$this->hasTable('reference_' . \preg_replace('/[^A-Za-z0-9_-]/', '', $payload['table']))) {
                throw new \InvalidArgumentException("Table reference_{$payload['table']} not found", 404);
            }
            
            $table = $payload['table'];
            $context['table'] = $table;
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $context['id'] = $id;
            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table);
            $deleted = $reference->deleteById($id);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "$table хүснэгтээс $id дугаартай [{$payload['keyword']}] түлхүүртэй лавлах мэдээллийг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Лавлах мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
}
