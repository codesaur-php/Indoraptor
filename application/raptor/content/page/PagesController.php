<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

class PagesController extends FileController
{
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $filters = [];
        $table = (new PagesModel($this->pdo))->getName();
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
        
        $this->indolog($table, LogLevel::NOTICE, 'Хуудас жагсаалтыг нээж үзэж байна', ['action' => 'index']);
    }
    
    public function list()
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $params = $this->getQueryParams();
            if (!isset($params['is_active'])) {
                $params['is_active'] = 1;
            }
            $conditions = [];
            $allowed = ['code', 'type', 'category', 'published', 'is_active'];
            foreach (\array_keys($params) as $name) {
                if (\in_array($name, $allowed)) {
                    $conditions[] = "$name=:$name";
                } else {
                    unset($params[$name]);
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
            $model = new PagesModel($this->pdo);
            $table = $model->getName();
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            } elseif ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['title'])){
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload['created_by'] = $this->getUserId();
                $payload['published'] = ($payload['published'] ?? 'off' ) == 'on' ? 1 : 0;
                if ($payload['published'] == 1) {
                    if (!$this->isUserCan('system_content_publish')
                    ) {
                        throw new \Exception($this->text('system-no-permission'), 401);
                    }
                    $payload['published_at'] = \date('Y-m-d H:i:s');
                    $payload['published_by'] = $this->getUserId();
                }
                $payload['comment'] = ($payload['comment'] ?? 'off' ) == 'on' ? 1 : 0;
                if (isset($payload['files'])) {
                    $files = \array_flip($payload['files']);
                    unset($payload['files']);
                }
                
                $insert = $model->insert($payload);
                if (!isset($insert['id'])) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $id = $insert['id'];
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $this->allowImageOnly();
                $this->setFolder("/$table/$id");
                $photo = $this->moveUploaded('photo');
                if ($photo) {
                    $record = $model->updateById($id, ['photo' => $photo['path']]);
                }
                $this->allowCommonTypes();
                if (!empty($files) && \is_array($files)) {
                    $html = $payload['content'];
                    \preg_match_all('/src="([^"]+)"/', $html, $srcs);
                    \preg_match_all('/href="([^"]+)"/', $html, $hrefs);
                    foreach (\array_keys($files) as $file_id) {
                        $update = $this->renameTo($table, $id, $file_id);
                        if (empty($update['path'])) {
                            continue;
                        }
                        $files[$file_id] = $update;
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
                    if ($html != $payload['content']) {
                        $record = $model->updateById($id, ['content' => $html]);
                    }
                    $record['files'] = $files;
                }
            } else {
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/page-insert.html',
                    [
                        'table' => $table,
                        'infos' => $this->getInfos($table),
                        'max_file_size' => $this->getMaximumFileUploadSize()
                    ]
                );
                $dashboard->set('title', $this->text('add-record') . ' | Pages');
                $dashboard->render();
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
        }  finally {
            $context = ['action' => 'create'];
            if (isset($e) && $e instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Шинэ хуудас үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            } elseif (isset($id)) {
                $level = LogLevel::INFO;
                $message = '{record_id} дугаартай шинэ хуудас [{record.title}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['record_id' => $id, 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Шинэ хуудас үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog($table ?? 'pages', $level, $message, $context);
        }
    }
    
    public function read(int $id)
    {
        try {
            $model = new PagesModel($this->pdo);
            $table = $model->getName();
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);
            $files = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
            $template = $this->twigTemplate(\dirname(__FILE__) . '/page-read.html');
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $template->set($key, $value);
            }
            $template->set('record', $record);
            $template->set('files', $files);
            $template->render();
            
            $model->updateById($id, ['read_count' => $record['read_count'] + 1]);
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
        } finally {
            $context = ['action' => 'read', 'record_id' => $id];
            if (isset($e) && $e instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хуудсыг унших үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record_id} дугаартай [{title}] хуудсыг уншиж байна';
                $context += $record + ['files' => $files];
            }
            $this->indolog($table ?? 'pages', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        try {
            $model = new PagesModel($this->pdo);
            $table = $model->getName();
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);
            $files = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
            $infos = $this->getInfos($table, "(id=$id OR id={$record['parent_id']})");
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/page-view.html',
                [
                    'table' => $table,
                    'record' => $record,
                    'files' => $files,
                    'infos' => $infos
                ]
            );
            $dashboard->set('title', $this->text('view-record') . ' | Pages');
            $dashboard->render();
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
        } finally {
            $context = ['action' => 'view', 'record_id' => $id];
            if (isset($e) && $e instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хуудасны мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record_id} дугаартай [{record.title}] хуудасны мэдээллийг нээж үзэж байна';
                $context += ['record' => $record, 'files' => $files];
            }
            $this->indolog($table ?? 'pages', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            $model = new PagesModel($this->pdo);
            $table = $model->getName();
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            } elseif ($record['published'] == 1 && !$this->isUserCan('system_content_publish')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['title'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload['published'] = ($payload['published'] ?? 'off' ) == 'on' ? 1 : 0;
                if ($payload['published'] != $record['published']) {
                    if (!$this->isUserCan('system_content_publish')) {
                        throw new \Exception($this->text('system-no-permission'), 401);
                    }
                    if ($payload['published'] == 1) {
                        $payload['published_at'] = \date('Y-m-d H:i:s');
                        $payload['published_by'] = $this->getUserId();
                    }
                }
                $payload['comment'] = ($payload['comment'] ?? 'off' ) == 'on' ? 1 : 0;

                $this->setFolder("/$table/$id");
                $this->allowImageOnly();
                $photo = $this->moveUploaded('photo');
                $current_photo_name = empty($record['photo']) ? '' : \basename($record['photo']);
                $payload['photo_removed'] = $payload['photo_removed'] ?? 0;
                if (!empty($current_photo_name)
                    && $payload['photo_removed'] == 1
                ) {
                    $this->unlinkByName($current_photo_name);
                    $current_photo_name = null;
                    $payload['photo'] = '';
                }
                if ($photo) {
                    if (!empty($current_photo_name)
                        && \basename($photo['path']) != $current_photo_name
                    ) {
                        $this->unlinkByName($current_photo_name);
                    }
                    $payload['photo'] = $photo['path'];
                }
                unset($payload['photo_removed']);
                
                $updates = [];
                foreach ($payload as $field => $value) {
                    if ($record[$field] != $value) {
                        $updates[] = $field;
                    }
                }
                $date = $record['updated_at'] ?? $record['created_at'];
                $count_updated_files =
                    "SELECT id FROM {$filesModel->getName()} " .
                    "WHERE record_id=$id AND (created_at > '$date' OR updated_at > '$date')";
                $files_changed = $filesModel->prepare($count_updated_files);
                if ($files_changed->execute()
                    && $files_changed->rowCount() > 0
                ) {
                    $updates[] = 'files';
                }
                if (empty($updates)) {
                    throw new \InvalidArgumentException('No update!');
                }
                
                $payload['updated_at'] = \date('Y-m-d H:i:s');
                $payload['updated_by'] = $this->getUserId();
                $updated = $model->updateById($id, $payload);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                $infos = $this->getInfos($table, "id!=$id AND parent_id!=$id");
                $files = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/page-update.html',
                    [
                        'table' => $table,
                        'record' => $record,
                        'infos' => $infos,
                        'files' => $files,
                        'max_file_size' => $this->getMaximumFileUploadSize()
                    ]
                );
                $dashboard->set('title', $this->text('edit-record') . ' | Pages');
                $dashboard->render();
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
        } finally {
            $context = ['action' => 'update', 'record_id' => $id];
            if (isset($e) && $e instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хуудасны мэдээллийг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            } elseif (!empty($updated)) {
                $level = LogLevel::INFO;
                $message = '{record_id} дугаартай [{record.title}] хуудасны мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.title}] хуудасны мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record, 'files' => $files];
            }
            $this->indolog($table ?? 'pages', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $model = new PagesModel($this->pdo);
            $table = $model->getName();
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
            $context = ['action' => 'deactivate'];
            if (isset($e) && $e instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хуудсыг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record_id} дугаартай [{server_request.body.title}] хуудсыг идэвхгүй болголоо';
                $context += ['record_id' => $id];
            }
            $this->indolog($table ?? 'pages', $level, $message, $context);
        }
    }
    
    private function getInfos(string $table, string $condition = ''): array
    {
        $pages = [];
        try {
            $select_pages = 
                'SELECT id, parent_id, title ' .
                "FROM $table WHERE is_active=1";
            $result = $this->query("$select_pages ORDER BY position, id")->fetchAll();
            foreach ($result as $record) {
                $pages[$record['id']] = $record;
            }
        } catch (\Throwable $e) {
        }
        if (!empty($condition)) {
            $pages_specified = [];
            try {
                $select_pages .= " AND $condition";
                $result_specified = $this->query("$select_pages ORDER BY position, id")->fetchAll();
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
}
