<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

class FilesController extends FileController
{    
    use \Raptor\Template\DashboardTrait;
    
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
            $table,
            LogLevel::NOTICE,
            '[{table}] файлын жагсаалтыг нээж үзэж байна',
            [
                'action' => 'files-index',
                'tables' => $tables,
                'total' => $total,
                'table' => $table
            ]
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
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
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
            $uploaded = $this->moveUploaded($input);
            if (!$uploaded) {
                throw new \InvalidArgumentException(__CLASS__ . ': Invalid upload!', 400);
            }
            if ($id > 0) {
                $uploaded['record_id'] = $id;
            }
            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $record = $model->insert($uploaded + ['created_by' => $this->getUserId()]);
            if (!isset($record['id'])) {
                throw new \Exception($this->text('record-insert-error'));
            }
            $this->respondJSON($record);
        } catch (\Throwable $err) {
            $error = ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            $this->respondJSON($error, $err->getCode());

            if (!empty($uploaded['file'])) {
                $this->unlinkByName(\basename($uploaded['file']));
            }
        } finally {
            if (!empty($record)) {
                $context = ['action' => 'files-post'] + $record;
                $message = '<a target="__blank" href="{path}">{path}</a> файлыг ';
                $message .= empty($record['record_id']) ?
                    'байршууллаа' : 'байршуулан {record_id}-р бичлэгт зориулж холболоо';
                $this->indolog($table, LogLevel::INFO, $message, $context);
            }
        }
    }
}
