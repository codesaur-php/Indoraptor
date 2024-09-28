<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

class LanguageController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function insert()
    {
        try {
            $context = ['model' => LanguageModel::class];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            if (!$this->isUserCan('system_localization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['copy'])
                    || empty($payload['code'])
                    || empty($payload['full'])
                ) {
                    throw new \Exception($this->text('invalid-values'), 400);
                }
                $context['payload'] = $payload;
                
                $model = new LanguageModel($this->pdo);
                $mother = $model->getRowBy(['code' => $payload['copy'], 'is_active' => 1]);
                if (!isset($mother['code'])) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                
                $languages = $model->retrieve();
                foreach ($languages as $key => $value) {
                    if ($payload['code'] == $key && $payload['full'] == $value) {
                        throw new \Exception($this->text('error-lang-existing'), 403);
                   }
                   if ($payload['code'] == $key) {
                        throw new \Exception($this->text('error-existing-lang-code'), 403);
                   }
                   if ($payload['full'] == $value) {
                        throw new \Exception($this->text('error-lang-name-existing'), 403);
                   }
                }
                
                $id = $model->insert([
                    'code' => $payload['code'],
                    'full' => $payload['full'],
                    'description' => $payload['description']
                ]);
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $context['record'] = $id;
                
                $this->copyMultimodelContent($mother['code'], $payload['code']);
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Шинэ хэл [{$payload['full']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";                
            } else {
                $this->twigTemplate(\dirname(__FILE__) . '/language-insert-modal.html')->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Шинэ хэл үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Шинэ хэл үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => LanguageModel::class];
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indoget('/record?model=' . LanguageModel::class, ['id' => $id]);
            $record['rbac_users'] = $this->retrieveUsers($record['created_by'], $record['updated_by']);
            $context['record'] = $record;
            $this->twigTemplate(\dirname(__FILE__) . '/language-retrieve-modal.html', ['record' => $record])->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['full']} хэлний мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Хэлний мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => LanguageModel::class];
            
            if (!$this->isUserCan('system_localization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['code'])
                    || empty($payload['full'])
                ) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                $record = [
                    'code' => $payload['code'],
                    'full' => $payload['full'],
                    'description' => $payload['description'] ?? null,
                    'is_default' => ($payload['is_default'] ?? 'off') != 'on' ? 0 : 1
                ];
                $context['record'] = $record;
                $context['record']['id'] = $id;

                try {
                    $defLanguage = $this->indo(
                        '/record?model=' . LanguageModel::class, ['is_default' => 1]
                    );
                } catch (\Throwable $e) {
                    $defLanguage = [];
                }
                
                if (isset($defLanguage['id'])
                    && $defLanguage['id'] != $id
                ) {
                    $this->indoput(
                        '/record?model=' . LanguageModel::class,
                        ['record' => ['is_default' => 0], 'condition' => ['WHERE' => "id={$defLanguage['id']}"]]
                    );
                }
                
                $updated = $this->indoput(
                    '/record?model=' . LanguageModel::class,
                    ['record' => $record, 'condition' => ['WHERE' => "id=$id"]]
                );
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "{$record['full']} хэлний мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indoget('/record?model=' . LanguageModel::class, ['id' => $id]);
                $this->twigTemplate(\dirname(__FILE__) . '/language-update-modal.html', ['record' => $record])->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "{$record['full']} хэлний мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = 'Хэлний мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => LanguageModel::class];
            
            if (!$this->isUserCan('system_localization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !isset($payload['name'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            try {
                $defLanguage = $this->indo(
                    '/record?model=' . LanguageModel::class, ['is_default' => 1]
                );
            } catch (\Throwable $e) {
                $defLanguage = [];
            }
            if (isset($defLanguage['id'])) {
                if ($defLanguage['id'] == $id) {
                    throw new \Exception('Cannot remove default language!', 403);
                }
            }
            $deleted = $this->indodelete("/record?model=" . LanguageModel::class, ['WHERE' => "id=$id"]);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} хэлний мэдээллийг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хэлний мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    private function copyMultimodelContent(string $from, string $to) 
    {
        try {
            $database = (string)$this->query('select database()')->fetchColumn();
            $stmt = $this->prepare("SHOW TABLES FROM $database LIKE " . $this->quote('%_content'));
            if (!$stmt->execute()) {
                throw new \Exception('There seems like no multimodel content tables!');
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
                $by_account = $this->getUser()->getProfile()['id'] ?? false;
                foreach ($table_columns as $column) {
                    $name = $column['Field'];
                    if ($name == 'id') {
                        $primary = true;
                    } elseif ($name == 'updated_at') {
                        $updates[] = 'updated_at=:at';
                    } elseif ($name == 'updated_by'
                            && $by_account !== false
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
                    $copied[] = [$table => $contentTable];
                }
            }

            if (!empty($copied)) {
                $this->indolog(
                    'localization',
                    LogLevel::ALERT,
                    __CLASS__ . " объект нь $from хэлнээс $to хэлийг хуулбарлан үүсгэлээ",
                    ['reason' => 'copy-multimodel-content', 'from' => $from, 'to' => $to, 'copied' => $copied]
                );
            }
        } catch (\Exception $e) {
            $this->errorLog($e);
        }        
    }
}
