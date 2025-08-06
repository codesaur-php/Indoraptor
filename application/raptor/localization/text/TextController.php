<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

class TextController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function insert(string $table)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            $context = ['model' => TextModel::class, 'table' => $table];
            
            if (!$this->isUserCan('system_localization_insert')) {
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
                
                $tables = $this->getTextTableNames();
                if (empty($record['keyword'])
                    || !\in_array($table, $tables)
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                $found = $this->findByKeyword($tables, $payload['keyword']);
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
                $id = $model->insert($record, $content);
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "$table хүснэгт дээр шинэ текст [{$payload['keyword']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $this->twigTemplate(\dirname(__FILE__) . '/text-insert-modal.html', ['table' => $table])->render();
                
                $level = LogLevel::NOTICE;
                $message = "$table хүснэгт дээр шинэ текст үүсгэх үйлдлийг эхлүүллээ";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = "$table хүснэгт дээр шинэ текст үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function view(string $table, int $id)
    {
        try {
            $context = ['id' => $id, 'model' => TextModel::class, 'table' => $table];
            
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
            $record['rbac_users'] = $this->retrieveUsersDetail($record['created_by'], $record['updated_by']);
            $context['record'] = $record;
            $this->twigTemplate(
                \dirname(__FILE__) . '/text-retrieve-modal.html',
                ['table' => $table, 'record' => $record]
            )->render();

            $level = LogLevel::NOTICE;
            $message = "$table хүснэгтээс [{$record['keyword']}] текст мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = "$table хүснэгтээс текст мэдээллийг нээж үзэх үед алдаа гарч зогслоо";
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update(string $table, int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => TextModel::class, 'table' => $table];

            if (!$this->isUserCan('system_localization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $tables = $this->getTextTableNames();
            if (!\in_array($table, $tables)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            
            $model = new TextModel($this->pdo);
            $model->setTable($table);
            $current = $model->getById($id);
            if (empty($current)) {
                throw new \Exception($this->text('no-record-selected'));
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
                
                if (empty($record['keyword'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $found = $this->findByKeyword($tables, $payload['keyword']);
                if (isset($found['table']) && isset($found['id'])
                    && ($found['id'] != $id || $found['table'] != $table)
                ) {
                    throw new \Exception(
                        $this->text('keyword-existing-in') . ' -> ID = ' .
                        $found['id'] . ', Table = ' . $found['table']);
                }
                
                $updated = $model->updateById($id, $record, $content);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'type' => 'primary',
                    'status' => 'success',
                    'message' => $this->text('record-update-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "$table хүснэгтийн [{$record['keyword']}] текст мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $this->twigTemplate(
                    \dirname(__FILE__) . '/text-update-modal.html',
                    ['record' => $current, 'table' => $table]
                )->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $current;
                $message = "$table хүснэгтээс [{$current['keyword']}] текст мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = "$table хүснэгтээс текст мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => TextModel::class];
            
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
            $context['payload'] = $payload;
            
            $model = new TextModel($this->pdo);
            $model->setTable($payload['table']);
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $deleted = $model->deleteById($id);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$model->getName()} хүснэгтээс [" . ($payload['keyword'] ?? $id) . '] текст мэдээллийг устгалаа';
        } catch (\Throwable $e) {
            $this->respondJSON([ 
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Текст мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    private function getTextTableNames(): array
    {
        $content_tables = $this->query(
            'SHOW TABLES LIKE ' . $this->quote('localization_text_%_content')
        )->fetchAll();
        
        $names = [];
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
