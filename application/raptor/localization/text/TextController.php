<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

class TextController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function insert(string $table)
    {
        try {
            if (!$this->isUserCan('system_localization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            if ($this->getRequest()->getMethod() == 'POST') {
                $payload = [];
                $content = [];
                $parsedBody = $this->getParsedBody();
                foreach ($parsedBody as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                        }
                    } else {
                        $payload[$index] = $value;
                    }
                }
                
                $tables = $this->getTextTableNames();
                if (empty($payload['keyword'])
                    || !\in_array($table, $tables)
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                $found = $this->findByKeyword($tables, $parsedBody['keyword']);
                if (isset($found['id'])
                    && !empty($found['table'])
                ) {
                    throw new \Exception(
                        $this->text('keyword-existing-in') . ' -> ID = ' .
                        $found['id'] . ', Table = ' . $found['table']
                    );
                }
                
                $model = new TextModel($this->pdo);
                $model->setTable($table);
                $record = $model->insert(
                    $payload + ['created_by' => $this->getUserId()], $content
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                $this->twigTemplate(
                    \dirname(__FILE__) . '/text-insert-modal.html',
                    ['table' => $table]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'text-create', 'table' => $table];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{table} хүснэгт дээр текст үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif (!empty($record)) {
                $level = LogLevel::INFO;
                $message = '{table} хүснэгт дээр текст [{record.keyword}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['id' => $record['id'], 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгт дээр текст үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function view(string $table, int $id)
    {
        try {
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $tables = $this->getTextTableNames();
            if (!\in_array($table, $tables)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            
            $model = new TextModel($this->pdo);
            $model->setTable($table);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $this->twigTemplate(
                \dirname(__FILE__) . '/text-retrieve-modal.html',
                ['table' => $table, 'record' => $record]
            )->render();
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = [
                'action' => 'text-view',
                'table' => $table,
                'id' => $id
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{table} хүснэгтийн {id} дугаартай текст мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгтээс [{record.keyword}] текст мэдээллийг нээж үзэж байна';
                $context += ['record' => $record];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update(string $table, int $id)
    {
        try {
            if (!$this->isUserCan('system_localization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }            
            $tables = $this->getTextTableNames();
            if (!\in_array($table, $tables)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }            
            $model = new TextModel($this->pdo);
            $model->setTable($table);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }            
            if ($this->getRequest()->getMethod() == 'PUT') {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody)) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload = [];
                $content = [];
                $updates = [];
                foreach ($parsedBody as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                            if ($record['localized'][$index][$key] != $value) {
                                $updates[] = "{$index}_{$key}";
                            }
                        }
                    } else {
                        $payload[$index] = $value;
                        if ($record[$index] != $value) {
                            $updates[] = $index;
                        }
                    }
                }
                if (empty($updates)) {
                    throw new \InvalidArgumentException('No update!');
                }
                if (empty($payload['keyword'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $found = $this->findByKeyword($tables, $parsedBody['keyword']);
                if (isset($found['table']) && isset($found['id'])
                    && ($found['id'] != $id || $found['table'] != $table)
                ) {
                    throw new \Exception(
                        $this->text('keyword-existing-in') . ' -> ID = ' .
                        $found['id'] . ', Table = ' . $found['table']);
                }
                $updated = $model->updateById(
                    $id, $payload + ['updated_by' => $this->getUserId()], $content
                );
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }                
                $this->respondJSON([
                    'type' => 'primary',
                    'status' => 'success',
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                $this->twigTemplate(
                    \dirname(__FILE__) . '/text-update-modal.html',
                    ['record' => $record, 'table' => $table]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = [
                'action' => 'text-update',
                'table' => $table,
                'id' => $id
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{table} хүснэгтээс {id} дугаартай текст мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif (!empty($updated)) {
                $level = LogLevel::INFO;
                $message = '{table} хүснэгтийн [{record.keyword}] текст мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгтээс [{record.keyword}] текст мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            if (!$this->isUserCan('system_localization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            $payload = $this->getParsedBody();
            $tables = $this->getTextTableNames();
            if (empty($payload['table'])
                || !\in_array($payload['table'], $tables)
                || !isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $model = new TextModel($this->pdo);
            $model->setTable($payload['table']);
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $deactivated = $model->deactivateById($id, [
                'updated_by' => $this->getUserId(), 'updated_at' => \date('Y-m-d H:i:s')
            ]);
            if (!$deactivated) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'text-deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Текст мэдээлэл идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{table} хүснэгтээс {id} дугаартай [{server_request.body.keyword}] текст мэдээллийг идэвхгүй болголоо';
                $context += ['table' => $payload['table'], 'id' => $id];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    private function getTextTableNames(): array
    {
        if ($this->getDriverName() == 'pgsql') {
            $query = 
                'SELECT tablename FROM pg_catalog.pg_tables ' .
                "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like 'localization_text_%_content'";
        } else {
            $query = 'SHOW TABLES LIKE ' . $this->quote('localization_text_%_content');
        }
        $names = [];
        $content_tables = $this->query($query)->fetchAll();
        foreach ($content_tables as $result) {
           $names[] = \substr(reset($result), \strlen('localization_text_'), -\strlen('_content'));
        }

        $initials = \get_class_methods(TextInitial::class);
        foreach ($initials as $value) {
            $initial = \substr($value, \strlen('localization_text_'));
            if (!empty($initial) && !\in_array($initial, $names)) {
                $names[] = $initial;
                (new TextModel($this->pdo))->setTable($initial);
            }
        }
        
        return $names;
    }
    
    private function findByKeyword(array $from, string $keyword): array|false
    {
        foreach ($from as $name) {
            $select = $this->prepare("SELECT * FROM localization_text_$name WHERE keyword=:1 LIMIT 1");
            $select->bindParam(':1', $keyword);
            if (!$select->execute()) {
                continue;
            }
            if ($select->rowCount() == 1) {
                return ['table' => $name] + $select->fetch();
            }
        }
        
        return false;
    }
}
