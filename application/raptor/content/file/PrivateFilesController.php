<?php

namespace Raptor\Content;

use Twig\TwigFilter;
use Psr\Log\LogLevel;

class PrivateFilesController extends FilesController
{
    public function setFolder(string $folder, bool $relative = true)
    {
        $script_path = $this->getScriptPath();
        $public_folder = "$script_path/private/file?name=$folder";
        
        $this->local = $this->getDocumentPath('/../private' . $folder);
        $this->public = $relative ? $public_folder : (string) $this->getRequest()->getUri()->withPath($public_folder);
    }
    
    public function getPath(string $fileName): string
    {
        return "$this->public/" . \urlencode($fileName);
    }
    
    public function read()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            $fileName = $this->getQueryParams()['name'] ?? '';
            $filePath = $this->getDocumentPath('/../private' . $fileName);
            if (empty($fileName) || !\file_exists($filePath)) {
                throw new \Exception('Not Found', 404);
            }

            $mimeType = \mime_content_type($filePath);
            if ($mimeType === false) {
                throw new \Exception('No Content', 204);
            }

            \header("Content-Type: $mimeType");
            \readfile($filePath);
        } catch (\Throwable $e) {
            $this->errorLog($e);
            $this->headerResponseCode($e->getCode());
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
        } catch (\Throwable $e) {
            $this->headerResponseCode($e->getCode());
            
            echo '<div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="alert alert-danger shadow-sm fade mt-3 show" role="alert">
                                <i class="bi bi-shield-fill-exclamation" style="margin-right:.3rem"></i>'
                            . $e->getMessage() .
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
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        } finally {
            if (empty($updated)) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай файлын бичлэгийг засах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = '{id} дугаартай [{path}] файлын мэдээллийг амжилттай засварлалаа';
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
                || !isset($payload['title'])
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
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
        } finally {
            if ($deactivated ?? false) {
                $level = LogLevel::ALERT;
                $message = '{id} дугаартай [{path}] файлыг идэвхгүй болголоо';
                if (!empty($record['record_id'])) {
                    $message = "{record_id}-р бичлэгт зориулсан $message";
                }
                $context = $record;
            } else {
                $level = LogLevel::ERROR;
                $message = 'Файлыг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            }
            $this->indolog($table, $level, $message, ['action' => 'deactivate-file']  + $context);
        }
    }
}
