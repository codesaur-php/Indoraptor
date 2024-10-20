<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

use Raptor\File\FilesModel;
use Raptor\File\FilesController;
use Raptor\File\FileController;

class PagesController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/pages-index.html');
        $dashboard->set('title', $this->text('pages'));
        $dashboard->render();
        
        $this->indolog('pages', LogLevel::NOTICE, 'Хуудас жагсаалтыг нээж үзэж байна', ['model' => PagesModel::class]);
    }
    
    public function list()
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $table = (new PagesModel($this->pdo))->getName();
            $select_pages = 
                'SELECT id, photo, title, code, category, type, position, published ' .
                "FROM $table WHERE is_active=1 ORDER BY position";
            $pages = $this->query($select_pages)->fetchAll();
            $infos = $this->getInfos($table);
            $files_counts = $this->getFilesCounts($table);            
            $this->respondJSON([
                'status' => 'success',
                'list' => $pages,
                'infos' => $infos,
                'files_counts' => $files_counts
            ]);
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }
    
    public function insert()
    {
        try {
            $context = ['model' => PagesModel::class];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new PagesModel($this->pdo);
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
                $message = "Шинэ хуудас [{$record['title']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
                
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
                $vars = [
                    'infos' => $this->getInfos($model->getName()),
                    'max_file_size' => $this->getMaximumFileUploadSize()
                ];
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/page-insert.html', $vars);
                $dashboard->set('title', $this->text('add-record') . ' | Pages');
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Шинэ хуудас үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Шинэ хуудас үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('pages', $level, $message, $context);
        }
    }
    
    public function read(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => PagesModel::class];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new PagesModel($this->pdo);
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
            
            $template = $this->twigTemplate(\dirname(__FILE__) . '/page-read.html');
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $template->set($key, $value);
            }
            foreach ($context as $key => $value) {
                $template->set($key, $value);
            }
            $template->render();

            $model->updateById($id, ['read_count' => $record['read_count'] + 1]);
            
            $level = LogLevel::NOTICE;
            $message = "{$record['title']} - хуудсыг уншиж байна";
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Хуудас унших үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('pages', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => PagesModel::class];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new PagesModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $record['rbac_users'] = $this->retrieveUsers($record['created_by'], $record['updated_by']);
            $context['record'] = $record;
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($model->getName());
            $context['files'] = $filesModel->getRows(
                ['WHERE' => "record_id=$id AND is_active=1"]
            );
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/page-view.html',
                $context + [
                    'infos' => $this->getInfos($model->getName(), "(id=$id OR id={$record['parent_id']})")
                ]
            );
            $dashboard->set('title', $this->text('view-record') . ' | Pages');
            $dashboard->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['title']} - хуудасны мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Хуудасны мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('pages', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => PagesModel::class];
            
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new PagesModel($this->pdo);
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
                $message = "{$record['title']} - хуудасны мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
                
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
                        'pages',
                        LogLevel::INFO,
                        "{$current['title']} хуудаст зориулж $fid дугаартай файлыг бүртгэлээ",
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
                $context['infos'] = $this->getInfos($model->getName(), "id!=$id AND parent_id!=$id");
                $context['max_file_size'] = $this->getMaximumFileUploadSize();
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/page-update.html', $context);
                $dashboard->set('title', $this->text('edit-record') . ' | Pages');
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = "{$context['record']['title']} - хуудасны мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = 'Хуудсыг засах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('pages', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => PagesModel::class];
            
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
            $model = new PagesModel($this->pdo);
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
            $message = "{$payload['title']} - хуудсыг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хуудсыг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('pages', $level, $message, $context);
        }
    }
    
    private function getInfos(string $table, string $condition = ''): array
    {
        $pages = [];
        try {
            $select_pages = 
                'SELECT id, parent_id, title ' .
                "FROM $table WHERE is_active=1";
            $result = $this->query($select_pages . ' ORDER BY position, id')->fetchAll();
            foreach ($result as $record) {
                $pages[$record['id']] = $record;
            }
        } catch (\Throwable $e) {
        }
        if (!empty($condition)) {
            $pages_specified = [];
            try {
                $select_pages .= " AND $condition";
                $result_specified = $this->query($select_pages . ' ORDER BY position, id')->fetchAll();
                foreach ($result_specified as $row) {
                    $pages_specified[$row['id']] = $row;
                }
            } catch (\Throwable $e) {
            }
        }
        foreach ($pages as $page) {
            $id = $page['id'];
            $ancestry = $this->findAncestry($id, $pages);
            if (\array_key_exists($id, $ancestry)) {
                unset($ancestry[$id]);
                
                \error_log(__CLASS__ . ": Page $id misconfigured with parenting path!");
            }
            if (empty($ancestry)) {
                continue;
            }
            
            $path = '';
            $ancestry_keys = \array_flip($ancestry);
            for ($i = \count($ancestry_keys); $i > 0; $i--) {
                $path .= "{$pages[$ancestry_keys[$i]]['title']} » ";
            }
            $pages[$id]['parent_titles'] = $path;
            if (isset($pages_specified[$id])) {
                $pages_specified[$id]['parent_titles'] = $path;
            }
        }
        
        return $pages_specified ?? $pages;
    }
    
    private function findAncestry(int $id, array $pages, array &$ancestry = []): array
    {
        $parent = $pages[$id]['parent_id'];
        if (empty($parent)
            || !isset($pages[$parent])
            || \array_key_exists($parent, $ancestry)
        ) {
            return $ancestry;
        }
        
        $ancestry[$parent] = \count($ancestry) + 1;
        return $this->findAncestry($parent, $pages, $ancestry);
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
