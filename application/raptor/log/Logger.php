<?php

namespace Raptor\Log;

use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;

use codesaur\DataObject\Column;

class Logger extends AbstractLogger
{
    use \codesaur\DataObject\TableTrait;
    
    private ?int $_created_by_once = null;
    
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->columns = [
            'id' => (new Column('id', 'bigint'))->primary(),
            'level' => (new Column('level', 'varchar', 16))->default(LogLevel::NOTICE),
            'message' => (new Column('message', 'text'))->notNull(),
            'context' => (new Column('context', 'mediumtext'))->notNull(),
            'created_at' => new Column('created_at', 'timestamp'),
            'created_by' => new Column('created_by', 'bigint')
        ];
    }
    
    public function setTable(string $name)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \Exception(__CLASS__ . ': Logger table name must be provided', 1103);
        }
        
        $this->name = "{$table}_log";
        if ($this->hasTable($this->name)) {
            return;
        }
        
        $this->createTable($this->name, $this->getColumns());
        $this->__initial();
    }
    
    public function setColumns(array $columns)
    {
        // prevents from changing column infos
        throw new \Exception(__CLASS__ . ": You can't change predefined columns of Logger table!");
    }
    
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->setForeignKeyChecks(true);
    }
    
    /**
     * {@inheritdoc}
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (empty($this->name)) {
            return;
        }
        
        $record = [
            'level' => (string) $level,
            'message' => $message,
            'created_at' => \date('Y-m-d H:i:s'),
            'context' => \json_encode($context, \JSON_INVALID_UTF8_SUBSTITUTE)
                ?: \json_encode(\mb_convert_encoding($context, 'UTF-8', 'UTF-8'))
                ?: ('{"log-context-write-error":"' . \addslashes(\json_last_error_msg()) . '"}')
        ];
        
        if (isset($this->_created_by_once)) {
            $record['created_by'] = $this->_created_by_once;
            $this->_created_by_once = null;
        }
        
        $column = $param = [];
        foreach (\array_keys($record) as $key) {
            $column[] = $key;
            $param[] = ":$key";
        }
        $columns = \implode(', ', $column);
        $params = \implode(', ', $param);
        
        $insert = $this->prepare("INSERT INTO $this->name($columns) VALUES($params)");
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        $insert->execute();
    }
    
    private function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!\is_array($val)) {
                $replace['{{ ' . $key . ' }}'] = $val;
            }
        }
        return \strtr($message, $replace);
    }
    
    public function createdByOnce(?int $id)
    {
        $this->_created_by_once = $id;
    }
    
    public function getLogs(array $condition = []): array
    {
        $rows = [];
        if (empty($condition)) {
            $condition['ORDER BY'] = 'id Desc';
        }
        $stmt = $this->selectStatement($this->getName(), '*', $condition);
        while ($record = $stmt->fetch()) {
            $record['context'] =
                \json_decode($record['context'], true, 100000, \JSON_INVALID_UTF8_SUBSTITUTE)
                ?? ['log-context-read-error' => \json_last_error_msg()];
            $record['message'] = $this->interpolate($record['message'], $record['context'] ?? []);
            $rows[$record['id']] = $record;
        }
        return $rows;
    }
    
    public function getLogById(int $id): array|null
    {
        $condition = [
            'LIMIT' => 1,
            'WHERE' => 'id=:id',
            'PARAM' => [':id' => $id]
        ];
        $stmt = $this->selectStatement($this->getName(), '*', $condition);
        if ($stmt->rowCount() != 1) {
            return null;
        }
        
        $record = $stmt->fetch();
        $record['context'] = 
            \json_decode($record['context'], true, 100000, \JSON_INVALID_UTF8_SUBSTITUTE)
            ?? ['log-context-read-error' => \json_last_error_msg()];
        $record['message'] =
            $this->interpolate($record['message'], $record['context'] ?? []);
        return $record;
    }
}
