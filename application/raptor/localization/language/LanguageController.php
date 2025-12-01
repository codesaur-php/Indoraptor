<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

class LanguageController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_localization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($this->getRequest()->getMethod() == 'POST') {
                $payload = $this->getParsedBody();
                if (empty($payload['copy'])
                    || empty($payload['code'])
                    || empty($payload['locale'])
                    || empty($payload['title'])
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-values'), 400);
                }
                
                $model = new LanguageModel($this->pdo);
                $mother = $model->getRowWhere([
                    'code' => $payload['copy'],
                    'is_active' => 1
                ]);
                if (!isset($mother['code'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                unset($payload['copy']);
                
                $languages = $model->retrieve();
                foreach ($languages as $key => $value) {
                    if ($payload['code'] == $key
                        && $payload['locale'] == $value['locale']
                        && $payload['title'] == $value['title']
                    ) {
                        throw new \Exception($this->text('error-lang-existing'), 403);
                   }
                   if ($payload['code'] == $key
                       || $payload['locale'] == $value['locale']
                   ) {
                        throw new \Exception($this->text('error-existing-lang-code'), 403);
                   }
                   if ($payload['title'] == $value['title']) {
                        throw new \Exception($this->text('error-lang-name-existing'), 403);
                   }
                }
                
                $record = $model->insert(
                    $payload + ['created_by' => $this->getUserId()]
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                $copied = $this->copyLocalizedContent($mother['code'], $payload['code']);         
            } else {
                $this->twigTemplate(__DIR__ . '/language-insert-modal.html')->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'language-create'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хэлний бичлэг үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = 'Хэл [{record.code} - {record.title}] амжилттай үүслээ';
                $context += [
                    'id' => $record['id'],
                    'record' => $record,
                    "copied-localized-content-{$mother['code']}-to-{$record['code']}" => $copied
                ];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Хэлний бичлэг үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        try {
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new LanguageModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            $this->twigTemplate(
                __DIR__ . '/language-retrieve-modal.html',
                ['record' => $record]
            )->render();
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'language-view', 'id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай хэлний мэдээллийг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.title} хэлний мэдээллийг үзэж байна';
                $context += ['record' => $record];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            if (!$this->isUserCan('system_localization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new LanguageModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }        
            if ($this->getRequest()->getMethod() == 'PUT') {
                $payload = $this->getParsedBody();
                if (empty($payload['code'])
                    || empty($payload['title'])
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload['is_default'] = ($payload['is_default'] ?? 'off') != 'on' ? 0 : 1;
                
                $updates = [];
                foreach ($payload as $field => $value) {
                    if ($record[$field] != $value) {
                        $updates[] = $field;
                    }
                }
                if (empty($updates)) {
                    throw new \InvalidArgumentException('No update!');
                }
                if ($record['is_default'] == 1
                    && $payload['is_default'] == 0
                ) {
                    throw new \InvalidArgumentException('You can\'t change default language!');
                }
                
                $updated = $model->updateById(
                    $id, $payload + ['updated_by' => $this->getUserId()]
                );
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
                
                if ($updated['is_default'] == 1) {
                    $model->exec(
                        "UPDATE {$model->getName()} " .
                        "SET is_default=0, updated_by={$updated['updated_by']}, updated_at={$model->quote($updated['updated_at'])} " .
                        "WHERE id<>{$updated['id']} AND is_default=1"
                    );
                }
            } else {
                $this->twigTemplate(
                    __DIR__ . '/language-update-modal.html',
                    ['record' => $record]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'language-update', 'id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хэлний мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '[{record.title}] хэлний мэдээллийг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '[{record.title}] хэлний мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function deactivate()
    {
        try {
            if (!$this->isUserCan('system_localization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            
            $model = new LanguageModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            if ($record['is_default'] == 1) {
                throw new \Exception('Cannot remove default language!', 403);
            }
            $deactivated = $model->deactivateById(
                $id,
                [
                    'updated_by' => $this->getUserId(),
                    'updated_at' => \date('Y-m-d H:i:s')
                ]
            );
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
            $context = ['action' => 'language-deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хэлний мэдээлэл идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{record.title} хэлний мэдээллийг [{server_request.body.reason}] шалтгаанаар идэвхгүй болголоо';
                $context += ['record' => $record];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    private function copyLocalizedContent(string $from, string $to): array|false
    {
        try {
            if ($this->getDriverName() == 'pgsql') {
                $query = 
                    'SELECT tablename FROM pg_catalog.pg_tables ' .
                    "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like '%_content'";
            } else {
                $query = 'SHOW TABLES LIKE ' . $this->quote('%_content');
            }
            $stmt = $this->prepare($query);
            if (!$stmt->execute()) {
                throw new \Exception('There seems like no localized content tables!');
            }

            $copied = [];
            while ($rows = $stmt->fetch()) {
                $contentTable = \current($rows);
                $query = $this->query("SHOW COLUMNS FROM $contentTable");
                $columns = $query->fetchAll();
                $id = $parent_id = $code = false;
                $field = $param = [];
                foreach ($columns as $column) {
                    $name = $column['Field'];
                    if ($name == 'id'
                        && $column['Extra'] == 'auto_increment'
                    ) {
                        $id = true;
                    } elseif ($name == 'parent_id') {
                        $parent_id = true;
                    } elseif ($name == 'code') {
                        $code = true;
                    } else {
                        $field[] = $name;
                        $param[] = ":$name";
                    }
                }

                if (
                    !$id
                    || !$parent_id
                    || !$code
                    || empty($field)
                ) {
                    continue;
                }

                $table = \substr($contentTable, 0, \strlen($contentTable) - 8);
                if (!$this->hasTable($table)) {
                    continue;
                }

                $table_query = $this->query("SHOW COLUMNS FROM $table");
                $table_columns = $table_query->fetchAll();
                $update = false;
                $primary = false;
                $updates = [];
                $update_arguments = [];
                $by_account = $this->getUserId();
                foreach ($table_columns as $column) {
                    $name = $column['Field'];
                    if ($name == 'id') {
                        $primary = true;
                    } elseif ($name == 'updated_at') {
                        $updates[] = 'updated_at=:at';
                    } elseif ($name == 'updated_by'
                            && !empty($by_account)
                    ) {
                        $updates[] = 'updated_by=:by';
                        $update_arguments = [':by' => $by_account];
                    }
                }

                if (!$primary) {
                    continue;
                }

                if (!empty($updates)) {
                    $sets = \implode(', ', $updates);
                    $update = $this->prepare("UPDATE $table SET $sets WHERE id=:id");
                }

                $fields = \implode(', ', $field);
                $select = $this->prepare("SELECT parent_id, code, $fields FROM $contentTable WHERE code=:1");
                if (!$select->execute([':1' => $from])) {
                    continue;
                }
                $inserted = false;
                $params = \implode(', ', $param);
                while ($row = $select->fetch()) {
                    $existing = $this->prepare("SELECT id FROM $contentTable WHERE parent_id=:1 AND code=:2");
                    $parameters = [':1' => $row['parent_id'], ':2' => $to];
                    if ($existing->execute($parameters)
                        && $existing->rowCount() > 0
                    ) {
                        continue;
                    }

                    $insert = $this->prepare("INSERT INTO $contentTable(parent_id, code, $fields) VALUES(:1, :2, $params)");
                    foreach ($field as $name) {
                        $parameters[":$name"] = $row[$name];
                    }
                    if ($insert->execute($parameters)) {
                        $inserted = true;
                        if ($update) {
                            $update_arguments[':id'] = $row['parent_id'];
                            $update_arguments[':at'] = \date('Y-m-d H:i:s');
                            $update->execute($update_arguments);
                        }
                    }
                }

                if ($inserted) {
                    $copied[$table] = $contentTable;
                }
            }            
            return $copied;
        } catch (\Exception $ex) {
            $this->errorLog($ex);
            return false;
        }        
    }
}
