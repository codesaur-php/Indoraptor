<?php

namespace Raptor\File;

use Psr\Log\LogLevel;

class FilesController extends FileController
{    
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $tblNames = $this->query("SHOW TABLES LIKE '%_files'")->fetchAll();
        $tables = [];
        $total = ['tables' => 0, 'rows' => 0, 'sizes' => 0];
        foreach ($tblNames as $result) {
            $table = \substr(\current($result), 0, -(\strlen('_files')));
            $rows = $this->query("SELECT COUNT(*) as count FROM {$table}_files WHERE is_active=1")->fetchAll();
            $sizes = $this->query("SELECT SUM(size) as size FROM {$table}_files WHERE is_active=1")->fetchAll();
            $count = $rows[0]['count'];
            $size = $sizes[0]['size'];
            ++$total['tables'];
            $total['rows'] += $count;
            $total['sizes'] += $size;
            $tables[$table] = ['count' => $count, 'size' => $this->formatSizeUnits($size)];
        }
        
        if (empty($tables['files'])) {
            $tables = ['files' => ['count' => 0, 'size' => 0]] + $tables;
        }
        
        if (isset($this->getQueryParams()['table'])) {
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '',  $this->getQueryParams()['table']);
        } elseif (!empty($tables)) {
            $keys = \array_keys($tables);
            $table = \reset($keys);
        } else {
            $this->dashboardProhibited('No file tables found!', 404)->render();
            return;
        }
        
        if (\file_exists(\dirname(__FILE__) . "/files-index-$table.html")) {
            $template = \dirname(__FILE__) . "/files-index-$table.html";
        } else {
            $template = \dirname(__FILE__) . '/files-index.html';
        }
        
        $total['sizes'] = $this->formatSizeUnits($total['sizes']);
        $dashboard = $this->twigDashboard(
            $template,
            [
                'total' => $total,
                'table' => $table,
                'tables' => $tables,
                'max_file_size' => $this->getMaximumFileUploadSize()
            ]
        );
        $dashboard->set('title', $this->text('files'));
        $dashboard->render();

        $this->indolog(
            $table, LogLevel::NOTICE, "$table файлын жагсаалтыг нээж үзэж байна",
            ['model' => FilesModel::class, 'tables' => $tables, 'total' => $total, 'table' => $table]
        );
    }
    
    public function list(string $table)
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $exists = $this->query( "SHOW TABLES LIKE '{$table}_files'")->fetchAll();
            if (empty($exists)) {
                $files = [];
            } else {
                $select_files = 
                    'SELECT id, record_id, file, path, size, type, mime_content_type, category, keyword, description, created_at ' .
                    "FROM {$table}_files WHERE is_active=1";
                $files = $this->query($select_files)->fetchAll();
            }
            $this->respondJSON(['status' => 'success', 'list' => $files]);
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }

    public function post(string $input, string $table, int $id)
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            $this->setFolder("/$table" . ($id == 0 ? '' : "/$id"));
            $this->allowCommonTypes();
            $uploaded = $this->moveUploaded($input, $table);
            if (!$uploaded) {
                throw new \InvalidArgumentException(__CLASS__ . ': Invalid upload!', 400);
            }

            $record = $uploaded;
            if ($id > 0) {
                $record['record_id'] = $id;
            }
            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $record['id'] = $model->insert($record);
            if ($record['id'] == false) {
                throw new \Exception($this->text('record-insert-error'));
            }
            
            $text = "$id-р бичлэгт зориулж {$record['id']} дугаартай файлыг байршуулан холболоо";
            $this->indolog($table, LogLevel::INFO, $text, ['reason' => 'insert-upload-file', 'table' => $table, 'record' => $record]);
            $this->respondJSON($record);
        } catch (\Throwable $e) {
            $error = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            $this->respondJSON($error, $e->getCode());

            if (!empty($uploaded['file'])) {
                $this->tryDeleteFile(\basename($uploaded['file']), $table);
            }
        }
    }
    
    public function moveToFolder(string $table, int $record_id, int $file_id, int $mode = 0755)
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }
            
            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $model->updateById($file_id, ['record_id' => $record_id]);
            $record = $model->getById($file_id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $this->setFolder("/$table/$record_id");
            $upload_path = "$this->local/";
            $file_name = \basename($record['file']);
            if (!\file_exists($upload_path) || !\is_dir($upload_path)) {
                \mkdir($upload_path, $mode, true);
            } else {
                $name = \pathinfo($file_name, \PATHINFO_FILENAME);
                $ext = \strtolower(\pathinfo($file_name, \PATHINFO_EXTENSION));
                $file_name = $this->uniqueName($upload_path, $name, $ext);
            }
            $newPath = $upload_path . $file_name;
            if (!\rename($record['file'], $newPath)) {
                throw new \Exception("Can't rename file [{$record['file']}] to [$newPath]");
            }
            $update = ['file' => $newPath, 'path' => $this->getPath($file_name)];
            $updated = $model->updateById($file_id, $update);
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->indolog(
                $table,
                LogLevel::INFO,
                "$record_id-р бичлэгт зориулж $file_id дугаартай файлыг бүртгэлээ",
                ['reason' => 'register-file', 'table' => $table, 'record_id' => $record_id, 'file_id' => $file_id]
            );
            $this->indolog(
                $table,
                LogLevel::INFO,
                "$record_id-р бичлэгт зориулcан $file_id дугаартай файлын байршил солигдлоо. <a target=\"__blank\" href=\"{$update['path']}\">{$update['path']}</a>",
                ['reason' => 'rename-file-folder', 'table' => $table, 'record' => $update + $record, 'mode' => $mode
            ]);
            return true;
        } catch (\Throwable $e) {
            $this->errorLog($e);
            return false;
        }
    }

    private function formatSizeUnits(?int $bytes): string
    {
        if ($bytes >= 1099511627776) {
            return \number_format($bytes / 1099511627776, 2) . 'tb';
        } elseif ($bytes >= 1073741824) {
            return \number_format($bytes / 1073741824, 2) . 'gb';
        } elseif ($bytes >= 1048576) {
            return \number_format($bytes / 1048576, 2) . 'mb';
        } elseif ($bytes >= 1024) {
            return \number_format($bytes / 1024, 2) . 'kb';
        } else {
            return $bytes . 'b';
        }
    }
    
    private function getMaximumFileUploadSize(): string
    {
        return $this->formatSizeUnits(
            \min(
                $this->convertPHPSizeToBytes(\ini_get('post_max_size')),
                $this->convertPHPSizeToBytes(\ini_get('upload_max_filesize'))
            )
        );
    }
    
    private function convertPHPSizeToBytes($sSize): int
    {
        $sSuffix = \strtoupper(\substr($sSize, -1));
        if (!\in_array($sSuffix, ['P','T','G','M','K'])){
            return (int)$sSize;
        }
        $iValue = \substr($sSize, 0, -1);
        switch ($sSuffix) {
            case 'P':
                $iValue *= 1024;
            case 'T':
                $iValue *= 1024;
            case 'G':
                $iValue *= 1024;
            case 'M':
                $iValue *= 1024;
            case 'K':
                $iValue *= 1024;
                break;
        }
        return (int)$iValue;
    }
}
