<?php

namespace Raptor\Content;

use Twig\TwigFilter;
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
    
    public function modal(string $table)
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $queryParams = $this->getQueryParams();
            $id = $queryParams['id'] ?? null;
            if (!isset($id) || !\is_numeric($id)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            
            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $uri = $this->getRequest()->getUri();
            $scheme = $uri->getScheme();
            $authority = $uri->getAuthority();
            $host = '';
            if ($scheme != '') {
                $host .= "$scheme:";
            }
            if ($authority != '') {
                $host .= "//$authority";
            }
            $modal = \preg_replace('/[^A-Za-z0-9_-]/', '', $queryParams['modal'] ?? 'null');
            $template = $this->twigTemplate(
                \dirname(__FILE__) . "/$modal-modal.html",
                ['table' => $table, 'record' => $record, 'host' => $host]
            );
            $template->addFilter(new TwigFilter('basename', function (string $path): string
            {
                return \basename($path);
            }));
            $template->render();
        } catch (\Throwable $err) {
            $this->headerResponseCode($err->getCode());
            
            echo '<div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="alert alert-danger shadow-sm fade mt-3 show" role="alert">
                                <i class="bi bi-shield-fill-exclamation" style="margin-right:.3rem"></i>'
                            . $err->getMessage() .
                            '</div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary shadow-sm" data-bs-dismiss="modal" type="button">' . $this->text('close') . '</button>
                        </div>
                    </div>
                </div>';
        }
    }
    
    public function update(string $table, int $id)
    {
        try {
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            
            $record = [];
            foreach ($payload as $k => $v) {
                if (\str_starts_with($k, 'file_')) {
                    $k = \substr($k, 5);
                }
                $record[$k] = $v;
            }

            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $record['updated_at'] = \date('Y-m-d H:i:s');
            $record['updated_by'] = $this->getUserId();
            $updated = $model->updateById($id, $record);
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            $this->respondJSON([
                'type' => 'primary',
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-update-success'),
                'record' => $updated
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            if (empty($updated)) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай файлын бичлэгийг засах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context = ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = '{id} дугаартай [{path}] файлын бичлэгийг амжилттай засварлалаа';
                if (!empty($updated['record_id'])) {
                    $message = "{record_id}-р бичлэгт зориулсан $message";
                }
                $context = $updated;
            }
            $this->indolog($table, $level, $message, ['action' => 'files-update', 'id' => $id]  + $context);
        }
    }
    
    public function delete(string $table)
    {
        try {
            if (!$this->isUserCan('system_content_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            
            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
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
            if ($deactivated ?? false) {
                $level = LogLevel::ALERT;
                $message = '{id} дугаартай [{path}] файлын бичлэгийг идэвхгүй болголоо. Бодит файл [{file}] устаагүй болно.';
                if (!empty($record['record_id'])) {
                    $message = "{record_id}-р бичлэгт зориулсан $message";
                }
                $context = $record;
            } else {
                $level = LogLevel::ERROR;
                $message = 'Файлын бичлэгийг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context = ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            }
            $this->indolog($table, $level, $message, ['action' => 'deactivate-file']  + $context);
        }
    }
}
