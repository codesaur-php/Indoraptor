<?php

namespace Raptor\Log;

use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;

use codesaur\DataObject\Column;

class Logger extends AbstractLogger
{
    use \codesaur\DataObject\TableTrait;
    
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->columns = [
            'id' => (new Column('id', 'bigint'))->primary(),
            'level' => (new Column('level', 'varchar', 16))->default(LogLevel::NOTICE),
            'message' => (new Column('message', 'text'))->notNull(),
            'context' => (new Column('context', 'mediumtext'))->notNull(),
            'created_at' => new Column('created_at', 'timestamp')
        ];
    }
    
    public function setTable(string $name)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \InvalidArgumentException(__CLASS__ . ': Logger table name must be provided', 1103);
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
        throw new \RuntimeException(__CLASS__ . ": You can't change predefined columns of Logger table!");
    }
    
    protected function __initial()
    {
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
    
    public function getLogs(array $condition = []): array
    {
        $rows = [];
        try {
            if (empty($condition)) {
                $condition['ORDER BY'] = 'id Desc';
            }
            $stmt = $this->selectStatement($this->getName(), '*', $condition);
            while ($record = $stmt->fetch()) {
                $record['context'] =
                    \json_decode($record['context'], true, 100000, \JSON_INVALID_UTF8_SUBSTITUTE)
                    ?? ['log-context-read-error' => \json_last_error_msg()];
                \array_walk_recursive($record['context'], [$this, 'hideSecret']);
                $record['message'] = $this->interpolate($record['message'], $record['context'] ?? []);
                $rows[] = $record;
            }
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($e->getMessage());
            }
        }
        return $rows;
    }
    
    public function getLogById(int $id): array|null
    {
        $stmt = $this->prepare("SELECT * from $this->name WHERE id=$id LIMIT 1");
        if (!$stmt->execute() || $stmt->rowCount() != 1) {
            return null;
        }
        
        $record = $stmt->fetch();
        $record['context'] = 
            \json_decode($record['context'], true, 100000, \JSON_INVALID_UTF8_SUBSTITUTE)
            ?? ['log-context-read-error' => \json_last_error_msg()];
        \array_walk_recursive($record['context'], [$this, 'hideSecret']);
        $record['message'] = $this->interpolate($record['message'], $record['context'] ?? []);
        return $record;
    }
    
    private function hideSecret(&$v, $k)
    {
        $key = \strtoupper($k);
        if (!empty($key)
            && (\in_array($key, ['JWT', 'TOKEN', 'PIN'])
                || \str_contains($key, 'PASSWORD')
            )
        ) {
            $v = '*** hidden info ***';
        }
    }
}
