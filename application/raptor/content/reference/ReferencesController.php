<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

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
        
        $this->indolog('content', LogLevel::NOTICE, 'Лавлах хүснэгтүүдийн жагсаалтыг нээж үзэж байна', ['action' => 'reference-index']);
    }
    
    public function insert(string $table)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            } elseif ($is_submit) {
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
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/reference-insert.html',
                    ['table' => $table]
                );
                $dashboard->set('title', $this->text('add-record') . ' | ' . \ucfirst($table));
                $dashboard->render();
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
        } finally {
            $context = [
                'action' => 'reference-create',
                'table' => $table
            ];
            if (isset($e) && $e instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Шинэ лавлах мэдээллийг [{table}] хүснэгтэд үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            } elseif (isset($insert['id'])) {
                $level = LogLevel::INFO;
                $message = 'Шинээр [{record.keyword}] түлхүүртэй лавлах мэдээллийг [{table}] хүснэгт дээр үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['id' => $insert['id'], 'record' => $insert];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Шинэ лавлах мэдээллийг {table} хүснэгт дээр үйлдлийг эхлүүллээ';
            }
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function view(string $table, int $id)
    {
        try {
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
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/reference-view.html',
                ['table' => $table, 'record' => $record]
            );
            $dashboard->set('title', $this->text('view-record') . ' | ' . \ucfirst($table));
            $dashboard->render();
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
        } finally {
            $context = [
                'action' => 'reference-view',
                'table' => $table,
                'id' => $id
            ];
            if (isset($e) && $e instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{table} хүснэгтээс {id} дугаартай лавлах мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгтийн {id} дугаартай [{record.keyword}] түлхүүртэй лавлах мэдээллийг нээж үзэж байна';
                $context += ['record' => $record];
            }
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function update(string $table, int $id)
    {
        try {
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            } elseif (!$this->hasTable("reference_$table")) {
                throw new \Exception("Table reference_$table not found", 404);
            }
            
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table);
            $current = $reference->getById($id);
            if (empty($current)) {
                throw new \Exception($this->text('no-record-selected'), 400);
            }
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload)) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $record = [];
                $content = [];
                $updates = [];
                foreach ($payload as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                            if ($current['localized'][$index][$key] != $value) {
                                $updates[] = "{$index}_{$key}";
                            }
                        }
                    } else {
                        $record[$index] = $value;
                        if ($current[$index] != $value) {
                            $updates[] = $index;
                        }
                    }
                }
                if (empty($updates)) {
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
            } else {
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/reference-update.html',
                    ['table' => $table, 'record' => $current]
                );
                $dashboard->set('title', $this->text('edit-record') . ' | ' . \ucfirst($table));
                $dashboard->render();
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
        } finally {
            $context = [
                'action' => 'reference-update',
                'table' => $table,
                'id' => $id
            ];
            if (isset($e) && $e instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{table} хүснэгтийн {id} дугаартай лавлах мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            } elseif (!empty($updated)) {
                $level = LogLevel::INFO;
                $message = '{table} хүснэгтийн {id} дугаартай [{record.keyword}] түлхүүртэй лавлах мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгтийн {id} дугаартай [{record.keyword}] түлхүүртэй лавлах мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $current];
            }
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            if (!$this->isUserCan('system_content_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
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
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table);
            $deleted = $reference->deactivateById($id, [
                'updated_by' => $this->getUserId(), 'updated_at' => \date('Y-m-d H:i:s')
            ]);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
        } finally {
            $context = ['action' => 'reference-deactivate'];
            if (isset($e) && $e instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Лавлах мэдээлэл идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгтээс {id} дугаартай [{server_request.body.keyword}] түлхүүртэй лавлах мэдээллийг идэвхгүй болголоо';
                $context += ['record_id' => $id];
            }
            $this->indolog('content', $level, $message, $context);
        }
    }
}
