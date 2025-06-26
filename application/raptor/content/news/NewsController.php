<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

use Raptor\File\FilesController;
use Raptor\File\FileController;
use Raptor\File\FilesModel;
use Raptor\Log\Logger;
use Raptor\Log\LogsController;

class NewsController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/news-index.html');
        $dashboard->set('title', $this->text('news'));
        $dashboard->render();
        
        $this->indolog('news', LogLevel::NOTICE, 'Мэдээний жагсаалтыг нээж үзэж байна', ['model' => NewsModel::class]);
    }
    
    public function list()
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $table = (new NewsModel($this->pdo))->getName();
            $select_news = 
                "SELECT id, photo, title, code, type, category, published, published_at, date(created_at) as created_date " .
                "FROM $table WHERE is_active=1 ORDER BY created_at desc";
            $news = $this->query($select_news)->fetchAll();
            $files_counts = $this->getFilesCounts($table);
            $this->respondJSON([
                'status' => 'success',
                'list' => $news,
                'files_counts' => $files_counts
            ]);
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }
    
    public function insert()
    {
        try {
            $context = ['model' => NewsModel::class];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new NewsModel($this->pdo);
            $table = $model->getName();
            if ($is_submit) {
                $record = $this->getParsedBody();
                $record['created_by'] = $this->getUserId();
                $context['payload'] = $record;
                
                if (empty($record['title'])){
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                $record['published'] = ($record['published'] ?? 'off' ) == 'on' ? 1 : 0;
                if ($record['published'] == 1) {
                    if (!$this->isUserCan('system_content_publish')
                    ) {
                        throw new \Exception($this->text('system-no-permission'), 401);
                    }
                    $record['published_at'] = \date('Y-m-d H:i:s');
                    $record['published_by'] = $this->getUserId();
                }
                
                $record['comment'] = ($record['comment'] ?? 'off' ) == 'on' ? 1 : 0;
                if ($record['comment'] == 1
                    && !$this->isUserCan('system_content_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
                
                if (isset($record['files'])) {
                    $files = $record['files'];
                    unset($record['files']);
                }
                
                $insert = $model->insert($record);
                if (!isset($insert['id'])) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $id = $insert['id'];
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                
                $level = LogLevel::INFO;
                $message = "Шинэ мэдээ [{$record['title']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
                $context['id'] = $id;
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/$table/$id");
                $file->allowImageOnly();
                $html = $record['content'];
                if (!empty($files)
                    && \is_array($files)
                ) {
                    \preg_match_all('/src="([^"]+)"/', $html, $srcs);
                    \preg_match_all('/href="([^"]+)"/', $html, $hrefs);
                    $filesController = new FilesController($this->getRequest());
                    foreach ($files as $file_id) {
                        $update = $filesController->moveToFolder($table, $id, $file_id);
                        if (!empty($update['path'])) {
                            foreach ($srcs[1] as $src) {
                                $src_updated = \str_replace("/$table/", "/$table/$id/", $src);
                                if (\str_contains($src_updated, $update['path'])) {
                                    $html = \str_replace($src, $src_updated, $html);
                                }
                            }
                            foreach ($hrefs[1] as $href) {
                                $href_updated = \str_replace("/$table/", "/$table/$id/", $href);
                                if (\str_contains($href_updated, $update['path'])) {
                                    $html = \str_replace($href, $href_updated, $html);
                                }
                            }
                        }
                    }
                    if ($html != $record['content']) {
                        $model->updateById($id, ['content' => $html]);
                        $context['payload']['content'] = $html;
                    }
                }
                
                $photo = $file->moveUploaded('photo', $table);
                if ($photo) {
                    $model->updateById($id, ['photo' => $photo['path']]);
                    $context['payload']['photo'] = $photo;
                }
            } else {
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/news-insert.html',
                    ['max_file_size' => $this->getMaximumFileUploadSize()]
                );
                $dashboard->set('title', $this->text('add-record') . ' | News');
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Шинэ мэдээ үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Шинэ мэдээ үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('news', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => NewsModel::class];
            
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new NewsModel($this->pdo);
            $table = $model->getName();
            $current = $model->getById($id);
            if (empty($current)) {
                throw new \Exception($this->text('no-record-selected'));
            } elseif ($current['published'] == 1 && !$this->isUserCan('system_content_publish')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);
            
            if ($is_submit) {
                $record = $this->getParsedBody();
                $context['payload'] = $record;
                
                if (empty($record['title'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                $record['published'] = ($record['published'] ?? 'off' ) == 'on' ? 1 : 0;
                if ($record['published'] != $current['published']) {
                    if (!$this->isUserCan('system_content_publish')) {
                        throw new \Exception($this->text('system-no-permission'), 401);
                    }
                    if ($record['published'] == 1) {
                        $record['published_at'] = \date('Y-m-d H:i:s');
                        $record['published_by'] = $this->getUserId();
                    }
                }
                $record['comment'] = ($record['comment'] ?? 'off' ) == 'on' ? 1 : 0;
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/$table/$id");
                $file->allowImageOnly();
                $photo = $file->moveUploaded('photo', $table);
                if ($photo) {
                    $record['photo'] = $photo['path'];
                }
                $current_photo_file = empty($current['photo']) ? '' : \basename($current['photo']);
                if (!empty($current_photo_file)) {
                    if ($file->getLastError() == -1) {
                        $file->tryDeleteFile($current_photo_file, $table);
                        $record['photo'] = '';
                    } elseif (isset($record['photo'])
                        && \basename($record['photo']) != $current_photo_file
                    ) {
                        $file->tryDeleteFile($current_photo_file, $table);
                    }
                }
                if (isset($record['photo'])) {
                    $context['record']['photo'] = $record['photo'];
                }
                
                $context['updates'] = [];
                foreach ($record as $field => $value) {
                    if ($current[$field] != $value) {
                        $context['updates'][] = $field;
                    }
                }
                
                $date = $current['updated_at'] ?? $current['created_at'];
                $count_updated_files =
                    "SELECT id FROM {$filesModel->getName()} " .
                    "WHERE is_active=1 AND record_id=$id " .
                    "AND (created_at > '$date' OR updated_at > '$date')";
                $files_changed = $filesModel->prepare($count_updated_files);
                if ($files_changed->execute() && $files_changed->rowCount() > 0) {
                    $context['updates'][] = 'files';
                }
                
                if (empty($context['updates'])) {
                    throw new \InvalidArgumentException('No update!');
                }
                
                $record['updated_at'] = \date('Y-m-d H:i:s');
                $record['updated_by'] = $this->getUserId();
                $updated = $model->updateById($id, $record);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "{$record['title']} - мэдээг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $context['record'] = $current;
                $context['files'] = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
                $context['max_file_size'] = $this->getMaximumFileUploadSize();
                
                $logger = new Logger($this->pdo);
                $logger->setTable($table);
                $condition = ['ORDER BY' => 'id Desc'];
                if ($this->getDriverName() == 'pgsql') {
                    $condition['WHERE'] =
                        '(context::json->>\'id\')::bigint=' . $id .
                        ' AND context::json->>\'model\'=' . $this->quote($context['model']);
                } else {
                    $condition['WHERE'] =
                        'JSON_EXTRACT(context, "$.id")=' . $id .
                        ' AND JSON_EXTRACT(context, "$.model")=' . $this->quote($context['model']);
                }
                $logs = $logger->getLogs($condition);
                \array_walk_recursive($logs, [LogsController::class, 'hideSecret']);
                
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/news-update.html',
                    $context + ['logs' => $logs, 'users' => $this->retrieveUsers()]
                );
                $dashboard->set('title', $this->text('edit-record') . ' | News');
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = "{$context['record']['title']} - мэдээг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = 'Мэдээг засах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('news', $level, $message, $context);
        }
    }
    
    public function read(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => NewsModel::class];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new NewsModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $context['record'] = $record;
            
            $files = new FilesModel($this->pdo);
            $files->setTable($model->getName());
            $context['files'] = $files->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
            
            $template = $this->twigTemplate(\dirname(__FILE__) . '/news-read.html');
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $template->set($key, $value);
            }
            foreach ($context as $key => $value) {
                $template->set($key, $value);
            }
            $template->render();
            
            $model->updateById($id, ['read_count' => $record['read_count'] + 1]);
            
            $level = LogLevel::NOTICE;
            $message = "{$record['title']} - мэдээг уншиж байна";
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Мэдээг унших үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('news', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => NewsModel::class];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new NewsModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $context['record'] = $record;
            
            $logger = new Logger($this->pdo);
            $logger->setTable($model->getName());
            $condition = ['ORDER BY' => 'id Desc'];
            if ($this->getDriverName() == 'pgsql') {
                $condition['WHERE'] =
                    '(context::json->>\'id\')::bigint=' . $id .
                    ' AND context::json->>\'model\'=' . $this->quote($context['model']);
            } else {
                $condition['WHERE'] =
                    'JSON_EXTRACT(context, "$.id")=' . $id .
                    ' AND JSON_EXTRACT(context, "$.model")=' . $this->quote($context['model']);
            }
            $logs = $logger->getLogs($condition);
            \array_walk_recursive($logs, [LogsController::class, 'hideSecret']);
                
            $files = new FilesModel($this->pdo);
            $files->setTable($model->getName());
            $context['files'] = $files->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/news-view.html',
                $context + ['logs' => $logs, 'users' => $this->retrieveUsers()]
            );
            $dashboard->set('title', $this->text('view-record') . ' | News');
            $dashboard->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['title']} - мэдээг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Мэдээг нээж үзэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('news', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => NewsModel::class];
            
            if (!$this->isUserCan('system_content_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !isset($payload['title'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            
            $model = new NewsModel($this->pdo);
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
            
            $level = LogLevel::ALERT;
            $message = "{$payload['title']} - мэдээг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Мэдээг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('news', $level, $message, $context);
        }
    }
    
    private function getFilesCounts(string $table): array
    {
        try {
            $files_count = 
                'SELECT n.id as id, COUNT(*) as files ' .
                "FROM $table as n INNER JOIN {$table}_files as f ON n.id=f.record_id " .
                'WHERE n.is_active=1 AND f.is_active=1 ' .
                'GROUP BY n.id';
            $result = $this->query($files_count)->fetchAll();
            $counts = [];
            foreach ($result as $count) {
                $counts[$count['id']] = $count['files'];
            }
            return $counts;
        } catch (\Throwable $e) {
            $this->errorLog($e);
            return [];
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
}
