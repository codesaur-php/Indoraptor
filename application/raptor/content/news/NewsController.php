<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

/**
 * Class NewsController
 *
 * Мэдээ (News) контент удирдах controller.
 *
 * Энэ controller нь:
 * - Мэдээний жагсаалт харуулах (index, list)
 * - Шинэ мэдээ үүсгэх (insert)
 * - Мэдээ шинэчлэх (update)
 * - Мэдээ унших (read)
 * - Мэдээний дэлгэрэнгүй мэдээлэл харуулах (view)
 * - Мэдээг идэвхгүй болгох (deactivate)
 * зэрэг үйлдлүүдийг гүйцэтгэнэ.
 *
 * @package Raptor\Content
 */
class NewsController extends FileController
{
    use \Raptor\Template\DashboardTrait;
    
    /**
     * Мэдээний жагсаалтын dashboard хуудсыг харуулах.
     *
     * Энэ method нь:
     * - Хэл (code), төрөл (type), ангилал (category), статус (published)
     *   зэрэг шүүлтийн сонголтуудыг бэлтгэнэ
     * - news-index.html template-ийг render хийнэ
     *
     * Permission: system_content_index
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $filters = [];
        // news хүснэгтийн нэрийг NewsModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
        $table = (new NewsModel($this->pdo))->getName();
        $codes_result = $this->query(
            "SELECT DISTINCT code FROM $table WHERE is_active=1"
        )->fetchAll();
        $languages = $this->getLanguages();
        $filters['code']['title'] = $this->text('language');
        foreach ($codes_result as $row) {
            $filters['code']['values'][$row['code']] = "{$languages[$row['code']]['title']} [{$row['code']}]";
        }
        $types_result = $this->query(
            "SELECT DISTINCT type FROM $table WHERE is_active=1"
         )->fetchAll();
        $filters['type']['title'] = $this->text('type');
        foreach ($types_result as $row) {
            $filters['type']['values'][$row['type']] = $row['type'];
        }
        $categories_result = $this->query(
            "SELECT DISTINCT category FROM $table WHERE is_active=1"
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
        $dashboard = $this->twigDashboard(__DIR__ . '/news-index.html', ['filters' => $filters]);
        $dashboard->set('title', $this->text('news'));
        $dashboard->render();
        
        $this->indolog($table, LogLevel::NOTICE, 'Мэдээний жагсаалтыг үзэж байна', ['action' => 'index']);
    }
    
    /**
     * Мэдээний жагсаалтыг JSON хэлбэрээр буцаах.
     *
     * Энэ method нь:
     * - Query parameter-уудаас шүүлтийн нөхцөлүүдийг авна
     *   (code, type, category, published, is_active)
     * - Мэдээний жагсаалтыг бүртгэлийн огноогоор буурахаар эрэмбэлэнэ
     * - Мэдээ бүрийн хавсаргасан файлын тоог тоолж буцаана
     *
     * Permission: system_content_index
     *
     * @return void JSON response буцаана
     * @throws \Exception Эрхгүй бол exception шидэнэ
     */
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
            // news хүснэгтийн нэрийг NewsModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
            $table = (new NewsModel($this->pdo))->getName();
            $select_pages = 
                'SELECT id, photo, title, code, type, category, published, published_at, date(created_at) as created_date ' .
                "FROM $table WHERE $where ORDER BY created_at desc";
            $news_stmt = $this->prepare($select_pages);
            foreach ($params as $name => $value) {
                $news_stmt->bindValue(":$name", $value);
            }
            $news = $news_stmt->execute() ? $news_stmt->fetchAll() : [];
            $files_counts = $this->getFilesCounts($table);
            $this->respondJSON([
                'status' => 'success',
                'list' => $news,
                'files_counts' => $files_counts
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }
    
    /**
     * Шинэ мэдээ үүсгэх.
     *
     * Энэ method нь:
     * - GET method: Мэдээ үүсгэх форм хуудсыг харуулна
     * - POST method: Шинэ мэдээний бичлэгийг үүсгэнэ
     *   - Гол зураг (photo) upload хийх боломжтой
     *   - Content доторх файлуудын path-ийг автоматаар шинэчлэнэ
     *   - Published, comment зэрэг статусыг тохируулна
     *
     * Permission:
     * - system_content_insert: Мэдээ үүсгэх эрх
     * - system_content_publish: Мэдээ нийтлэх эрх (published=1 үед)
     *
     * @return void
     * @throws \Exception Эрхгүй эсвэл буруу хүсэлт бол exception шидэнэ
     */
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new NewsModel($this->pdo);
            $table = $model->getName();
            if ($this->getRequest()->getMethod() == 'POST') {
                $payload = $this->getParsedBody();
                if (empty($payload['title'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                if (($payload['published'] ?? 0 ) == 1) {
                    if (!$this->isUserCan('system_content_publish')
                    ) {
                        throw new \Exception($this->text('system-no-permission'), 401);
                    }
                    $payload['published_at'] = \date('Y-m-d H:i:s');
                    $payload['published_by'] = $this->getUserId();
                }
                if (($payload['comment'] ?? 0) == 1
                    && !$this->isUserCan('system_content_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
                if (isset($payload['files'])) {
                    $files = \array_flip($payload['files']);
                    unset($payload['files']);
                }
                
                $record = $model->insert($payload + ['created_by' => $this->getUserId()]);
                if (!isset($record['id'])) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $id = $record['id'];
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $this->allowImageOnly();
                $this->setFolder("/$table/$id");
                $photo = $this->moveUploaded('photo');
                if ($photo) {
                    $record = $model->updateById(
                        $id,
                        [
                            'photo' => $photo['path'],
                            'photo_size' => $photo['size']
                        ]
                    );
                }
                $this->allowCommonTypes();
                if (!empty($files) && \is_array($files)) {
                    $html = $record['content'];
                    \preg_match_all('/src="([^"]+)"/', $html, $srcs);
                    \preg_match_all('/href="([^"]+)"/', $html, $hrefs);
                    foreach (\array_keys($files) as $file_id) {
                        $update = $this->renameTo($table, $id, $file_id);
                        if (!$update) continue;
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
                    if ($html != $record['content']) {
                        $record = $model->updateById($id, ['content' => $html]);
                    }
                    $record['files'] = $files;
                }
            } else {
                $dashboard = $this->twigDashboard(
                    __DIR__ . '/news-insert.html',
                    [
                        'table' => $table,
                        'max_file_size' => $this->getMaximumFileUploadSize()
                    ]
                );
                $dashboard->set('title', $this->text('add-record') . ' | News');
                $dashboard->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'create'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Мэдээ үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.title}] мэдээг амжилттай үүсгэлээ';
                $context += ['record_id' => $id, 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Мэдээ үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog($table ?? 'news', $level, $message, $context);
        }
    }
    
    /**
     * Мэдээний бичлэгийг шинэчлэх.
     *
     * Энэ method нь:
     * - GET method: Мэдээ шинэчлэх форм хуудсыг харуулна
     * - PUT method: Мэдээний бичлэгийг шинэчлэнэ
     *   - Өөрчлөлт байгаа эсэхийг шалгана (No update! exception)
     *   - Гол зураг (photo) шинэчлэх, устгах боломжтой
     *   - Хавсаргасан файлууд өөрчлөгдсөн эсэхийг шалгана
     *   - Published статус өөрчлөгдсөн эсэхийг шалгаж, нийтлэх эрх шаардлагатай
     *
     * Permission:
     * - system_content_update: Мэдээ шинэчлэх эрх
     * - system_content_publish: Мэдээ нийтлэх эрх (published статус өөрчлөх үед)
     *
     * @param int $id Шинэчлэх мэдээний ID дугаар
     * @return void
     * @throws \Exception Эрхгүй, бичлэг олдохгүй эсвэл өөрчлөлт байхгүй бол exception шидэнэ
     */
    public function update(int $id)
    {
        try {
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new NewsModel($this->pdo);
            $table = $model->getName();
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);            
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            } elseif ($record['published'] == 1 && !$this->isUserCan('system_content_publish')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $payload = $this->getParsedBody();
                if (empty($payload['title'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload['photo_removed'] = $payload['photo_removed'] ?? 0;
                $payload['published'] = \filter_var($payload['published'] ?? 0, \FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                if ($payload['published'] != $record['published']) {
                    if (!$this->isUserCan('system_content_publish')) {
                        throw new \Exception($this->text('system-no-permission'), 401);
                    }
                    if ($payload['published'] == 1) {
                        $payload['published_at'] = \date('Y-m-d H:i:s');
                        $payload['published_by'] = $this->getUserId();
                    }
                }
                $payload['comment'] = \filter_var($payload['comment'] ?? 0, \FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                
                $this->setFolder("/$table/$id");
                $this->allowImageOnly();
                $photo = $this->moveUploaded('photo');
                $current_photo_name = empty($record['photo']) ? '' : \basename($record['photo']);
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
                $files = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
                $dashboard = $this->twigDashboard(
                    __DIR__ . '/news-update.html',
                    [
                        'table' => $table,
                        'record' => $record,
                        'files' => $files,
                        'max_file_size' => $this->getMaximumFileUploadSize()
                    ]
                );
                $dashboard->set('title', $this->text('edit-record') . ' | News');
                $dashboard->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'update', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай мэдээг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.title}] мэдээг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.title}] мэдээг шинэчлэхээр нээж байна';
                $context += ['record' => $record, 'files' => $files];
            }
            $this->indolog($table ?? 'news', $level, $message, $context);
        }
    }
    
    /**
     * Мэдээний бичлэгийг унших.
     *
     * Энэ method нь:
     * - Мэдээний бүрэн мэдээллийг харуулна
     * - Хавсаргасан файлуудыг харуулна
     * - Уншсан тооллогыг (read_count) нэмэгдүүлнэ
     * - news-read.html template ашиглана
     *
     * Permission: system_content_index
     *
     * @param int $id Унших мэдээний ID дугаар
     * @return void
     * @throws \Exception Эрхгүй эсвэл бичлэг олдохгүй бол exception шидэнэ
     */
    public function read(int $id)
    {
        try {
            $model = new NewsModel($this->pdo);
            $table = $model->getName();
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);
            $files = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);            
            $template = $this->twigTemplate(__DIR__ . '/news-read.html');
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $template->set($key, $value);
            }
            $template->set('record', $record);
            $template->set('files', $files);
            $template->render();            
            $model->updateById($id, ['read_count' => $record['read_count'] + 1]);
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'read', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай мэдээг унших үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record_id} дугаартай [{title}] мэдээг уншиж байна';
                $context += $record + ['files' => $files];
            }
            $this->indolog($table ?? 'news', $level, $message, $context);
        }
    }
    
    /**
     * Мэдээний дэлгэрэнгүй мэдээллийг dashboard-д харуулах.
     *
     * Энэ method нь:
     * - Мэдээний бүрэн мэдээллийг харуулна
     * - Хавсаргасан файлуудыг харуулна
     * - news-view.html template ашиглана
     *
     * Permission: system_content_index
     *
     * @param int $id Үзэх мэдээний ID дугаар
     * @return void
     * @throws \Exception Эрхгүй эсвэл бичлэг олдохгүй бол exception шидэнэ
     */
    public function view(int $id)
    {
        try {
            $model = new NewsModel($this->pdo);
            $table = $model->getName();
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);
            $files = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
            $dashboard = $this->twigDashboard(
                __DIR__ . '/news-view.html',
                ['table' => $table, 'record' => $record, 'files' => $files]
            );
            $dashboard->set('title', $this->text('view-record') . ' | News');
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'view', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай мэдээг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.title}] мэдээг үзэж байна';
                $context += ['record' => $record, 'files' => $files];
            }
            $this->indolog($table ?? 'news', $level, $message, $context);
        }
    }
    
    /**
     * Мэдээний бичлэгийг идэвхгүй болгох.
     *
     * Энэ method нь:
     * - Request body-оос id дугаарыг авна
     * - Мэдээний бичлэгийг is_active=0 болгон шинэчлэнэ
     * - updated_by, updated_at талбаруудыг автоматаар шинэчлэнэ
     *
     * Permission: system_content_delete
     *
     * @return void JSON response буцаана
     * @throws \Exception Эрхгүй, буруу хүсэлт эсвэл бичлэг олдохгүй бол exception шидэнэ
     */
    public function deactivate()
    {
        try {
            $model = new NewsModel($this->pdo);
            $table = $model->getName();
            
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
            $deactivated = $model->deactivateById(
                $id,
                [
                    'updated_by' => $this->getUserId(),
                    'updated_at' => \date('Y-m-d H:i:s')
                ]
            );
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
            $context = ['action' => 'deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Мэдээг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{record_id} дугаартай [{server_request.body.title}] мэдээг идэвхгүй болголоо';
                $context += ['record_id' => $id];
            }
            $this->indolog($table ?? 'news', $level, $message, $context);
        }
    }
    
    /**
     * Мэдээ бүрийн хавсаргасан идэвхтэй файлын тоог тоолох.
     *
     * Энэ method нь:
     * - Мэдээний хүснэгт (news) болон файлын хүснэгт (news_files) хооронд
     *   INNER JOIN хийж файлын тоог тоолно
     * - Зөвхөн идэвхтэй (is_active=1) бичлэг болон файлуудыг тоолно
     * - Үр дүнг [id => files_count] хэлбэртэй массив хэлбэрээр буцаана
     *
     * @param string $table Хүснэгтийн нэр (жишээ: 'news')
     * @return array Мэдээний ID => Файлын тоо бүтэцтэй массив
     */
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
        } catch (\Throwable) {
            return [];
        }
    }
}
