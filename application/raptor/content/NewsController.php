<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

use Raptor\File\FilesController;
use Raptor\File\FileController;
use Raptor\File\FilesModel;

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
                'SELECT id, photo, title, code, category, type, published, published_at ' .
                "FROM $table WHERE is_active=1 ORDER BY published_at desc";
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
            if ($is_submit) {
                $record = $this->getParsedBody();
                $context['payload'] = $record;
                
                if (empty($record['published_at'])) {
                    $record['published_at'] = \date('Y-m-d H:i:s');
                }
                $record['published'] = ($record['published'] ?? 'off' ) == 'on' ? 1 : 0;
                
                if (isset($record['files'])) {
                    $files = $record['files'];
                    unset($record['files']);
                }
                
                if ($record['published'] == 1
                    && !$this->isUserCan('system_content_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
                
                $id = $model->insert($record);
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/{$model->getName()}/$id");
                $file->allowImageOnly();
                $photo = $file->moveUploaded('photo', $model->getName());
                if ($photo) {
                    $model->updateById($id, ['photo' => $photo['path']]);
                    $context['photo'] = $photo;
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Шинэ мэдээ [{$record['title']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
                
                if (!isset($files)
                    || empty($files)
                    || !\is_array($files)
                ) {
                    return;
                }
                $filesController = new FilesController($this->getRequest());
                foreach ($files as $file_id) {
                    $filesController->moveToFolder($model->getName(), $id, (int)$file_id);
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
            $context['files'] = $files->getRows(
                [
                    'WHERE' => "record_id=$id AND is_active=1",
                    'ORDER BY' => 'updated_at'
                ]
            );
            
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
            $record['rbac_users'] = $this->retrieveUsers($record['created_by'], $record['updated_by']);
            $context['record'] = $record;
            
            $files = new FilesModel($this->pdo);
            $files->setTable($model->getName());
            $context['files'] = $files->getRows(
                [
                    'WHERE' => "record_id=$id AND is_active=1",
                    'ORDER BY' => 'updated_at'
                ]
            );
            
            $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/news-view.html', $context);
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
    
    public function update(int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => NewsModel::class];
            
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new NewsModel($this->pdo);
            $current = $model->getById($id);
            if (empty($current)) {
                throw new \Exception($this->text('no-record-selected'));
            }
                
            if ($is_submit) {
                $record = $this->getParsedBody();
                $context['payload'] = $record;
                
                $record['published'] = ($record['published'] ?? 'off' ) == 'on' ? 1 : 0;
                
                if (isset($record['files'])) {
                    $files = $record['files'];
                    unset($record['files']);
                }
                
                if (empty($record['title'])){
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                if ($record['published'] != $current['published']
                    && !$this->isUserCan('system_content_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/{$model->getName()}/$id");
                $file->allowImageOnly();
                $photo = $file->moveUploaded('photo', $model->getName());
                if ($photo) {
                    $record['photo'] = $photo['path'];
                }
                $current_photo_file = empty($current['photo']) ? '' : \basename($current['photo']);
                if (!empty($current_photo_file)) {
                    if ($file->getLastError() == -1) {
                        $file->tryDeleteFile($current_photo_file, $model->getName());
                        $record['photo'] = '';
                    } elseif (isset($record['photo'])
                        && \basename($record['photo']) != $current_photo_file
                    ) {
                        $file->tryDeleteFile($current_photo_file, $model->getName());
                    }
                }
                if (isset($record['photo'])) {
                    $context['record']['photo'] = $record['photo'];
                }
                
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
                
                if (!isset($files)
                    || empty($files)
                    || !\is_array($files)
                ) {
                    return;
                }
                
                $filesModel = new FilesModel($this->pdo);
                $filesModel->setTable($model->getName());
                $current_files = $filesModel->getRows(
                    ['WHERE' => "record_id=$id AND is_active=1"]
                );
                foreach ($files as $file_id) {
                    $fid = (int) $file_id;
                    if (\array_key_exists($fid, $current_files)) {
                        continue;
                    }
                    $filesModel->updateById($fid, ['record_id' => $id]);
                    $this->indolog(
                        'news',
                        LogLevel::INFO,
                        "{$current['title']} мэдээнд зориулж $fid дугаартай файлыг бүртгэлээ",
                        ['reason' => 'register-file', 'table' => $model->getName(), 'record_id' => $id, 'file_id' => $fid]
                    );
                }
            } else {
                $context['record'] = $current;
                $context['record']['rbac_users'] = $this->retrieveUsers($current['created_by'], $current['updated_by']);
                $filesModel = new FilesModel($this->pdo);
                $filesModel->setTable($model->getName());
                $context['files'] = $filesModel->getRows(
                    ['WHERE' => "record_id=$id AND is_active=1"]
                );
                $context['max_file_size'] = $this->getMaximumFileUploadSize();
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/news-update.html', $context);
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
                'GROUP BY f.record_id';
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
