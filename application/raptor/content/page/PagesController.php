<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

use Raptor\File\FilesModel;
use Raptor\File\FilesController;
use Raptor\File\FileController;
use Raptor\Log\Logger;
use Raptor\Log\LogsController;

class PagesController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $model = new PagesModel($this->pdo);
        $table = $model->getName();
        
        $filters = [];
        $codes_result = $this->query(
            "SELECT DISTINCT (code) FROM $table WHERE is_active=1"
        )->fetchAll();
        $languages = $this->getLanguages();
        $filters['code']['title'] = $this->text('language');
        foreach ($codes_result as $row) {
            $filters['code']['values'][$row['code']] = "{$languages[$row['code']]} [{$row['code']}]";
        }
        $types_result = $this->query(
            "SELECT DISTINCT (type) FROM $table WHERE is_active=1"
        )->fetchAll();
        $filters['type']['title'] = $this->text('type');
        foreach ($types_result as $row) {
            $filters['type']['values'][$row['type']] = $row['type'];
        }
        $categories_result = $this->query(
            "SELECT DISTINCT (category) FROM $table WHERE is_active=1"
        )->fetchAll();
        $filters['category']['title'] = $this->text('category');
        foreach ($categories_result as $row) {
            $filters['category']['values'][$row['category']] = $row['category'];
        }
        $filters += [
            'published' => [
                'title' => $this->text('status'),
                'values' => [
                    0 => 'unpublished',
                    1 => 'published'
                ]
            ]
        ];

        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/pages-index.html', ['filters' => $filters]);
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
            
            $params = $this->getQueryParams() + ['is_active' => 1];
            $conditions = [];
            $allowed = ['code', 'type', 'category', 'published', 'is_active'];
            foreach (\array_keys($params) as $name) {
                if (\in_array($name, $allowed)) {
                    $conditions[] = "$name=:$name";
                }
            }
            $where = \implode(' AND ', $conditions);
         
            $table = (new PagesModel($this->pdo))->getName();
            $select_pages = 
                'SELECT id, photo, title, code, type, category, position, link, published, published_at, is_active ' .
                "FROM $table WHERE $where ORDER BY position, id";
            $pages_stmt = $this->prepare($select_pages);
            foreach ($params as $name => $value) {
                $pages_stmt->bindValue(":$name", $value);
            }
            $pages = $pages_stmt->execute() ? $pages_stmt->fetchAll() : [];
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
                
                $context['id'] = $id;
                $level = LogLevel::INFO;
                $message = "Шинэ хуудас [{$record['title']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/$table/$id");
                $file->allowImageOnly();                
                if (!empty($files)
                    && \is_array($files)
                ) {
                    $html = $record['content'];
                    \preg_match_all('/src="([^"]+)"/', $html, $srcs);
                    \preg_match_all('/href="([^"]+)"/', $html, $hrefs);
                    $filesController = new FilesController($this->getRequest());
                    foreach ($files as $file_id) {
                        $update = $filesController->moveToFolder($table, $id, $file_id);
                        if (!empty($update['path'])) {
                            foreach ($srcs[1] as $src) {
                                $src_path = \str_replace("/$table/", "/$table/$id/", $src);
                                if ($src_path == $update['path']) {
                                    $html = \str_replace($src, $update['path'], $html);
                                    continue;
                                }
                            }
                            foreach ($hrefs[1] as $href) {
                                $href_path = \str_replace("/$table/", "/$table/$id/", $href);
                                if ($href_path == $update['path']) {
                                    $html = \str_replace($href, $update['path'], $html);
                                    continue;
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
                    $context['photo'] = $photo;
                }
            } else {
                $vars = [
                    'infos' => $this->getInfos($table),
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
            $context['files'] = $files->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
            
            $template = $this->twigTemplate(\dirname(__FILE__) . '/page-read.html');
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $template->set($key, $value);
            }
            foreach ($context as $key => $value) {
                $template->set($key, $value);
            }
            $template->render();

            $model->updateById($id, ['read_count' => $record['read_count'] + 1]);
            
            $context['id'] = $id;
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
            $context['record'] = $record;
            
            $logger = new Logger($this->pdo);
            $logger->setTable('pages');
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
            $context['infos'] = $this->getInfos($model->getName(), "(id=$id OR id={$record['parent_id']})");
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/page-view.html',
                $context + ['logs' => $logs, 'users_detail' => $this->retrieveUsersDetail()]
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
                
                if (empty($record['title'])){
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
                    "WHERE record_id=$id AND (created_at > '$date' OR updated_at > '$date')";
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
                $message = "{$record['title']} - хуудасны мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $context['record'] = $current;
                $context['files'] = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
                $context['max_file_size'] = $this->getMaximumFileUploadSize();
                $context['infos'] = $this->getInfos($table, "id!=$id AND parent_id!=$id");
                
                $logger = new Logger($this->pdo);
                $logger->setTable('pages');
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
                    \dirname(__FILE__) . '/page-update.html',
                    $context + ['logs' => $logs, 'users_detail' => $this->retrieveUsersDetail()]
                );
                $dashboard->set('title', $this->text('edit-record') . ' | Pages');
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = "{$current['title']} - хуудасны мэдээллийг шинэчлэхээр нээж байна";
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
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $context['id'] = $id;
            
            $model = new PagesModel($this->pdo);
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
            $message = "{$payload['title']} - хуудсыг идэвхгүй болголоо";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хуудсыг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
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
