<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;
use Twig\TwigFilter;

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
     *   - Гол зураг (photo) заах боломжтой
     *   - Content доторх файлуудын path-ийг автоматаар шинэчлэнэ
     *   - Хавсралт файлуудыг заана
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
                
                // Нийтлэх эрх шаардлагатай талбарууд
                $isPublished = ($payload['published'] ?? 0) == 1;
                $needsPublishPermission =
                    $isPublished ||
                    ($payload['is_featured'] ?? 0) == 1 ||
                    ($payload['comment'] ?? 0) == 1;
                if ($needsPublishPermission
                    && !$this->isUserCan('system_content_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }

                if ($isPublished) {
                    $payload['published_at'] = \date('Y-m-d H:i:s');
                    $payload['published_by'] = $this->getUserId();
                }

                // Model-д байхгүй талбаруудыг payload-оос салгах
                $files = \json_decode($payload['files'] ?? '{}', true) ?: [];
                unset($payload['files']);

                $record = $model->insert(
                    $payload + ['created_by' => $this->getUserId()]
                );
                if (!isset($record['id'])) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $id = $record['id'];

                // Файлуудыг нэгдсэн аргаар боловсруулах
                $fileChanges = $this->processFiles($record, $files, true);

                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
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
                $context['file_changes'] = !empty($fileChanges) ? $fileChanges : 'files not changed';
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
            } elseif ($record['published'] == 1
                && !$this->isUserCan('system_content_publish')
            ) {
                // Нийтлэгдсэн бичлэгийг засахад publish эрх шаардлагатай
                throw new \Exception($this->text('system-no-permission'), 401);
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $payload = $this->getParsedBody();
                if (empty($payload['title'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }

                // Нийтлэх эрх шаардлагатай талбарууд
                $isPublished = ($payload['published'] ?? 0) == 1;
                $needsPublishPermission =
                    $isPublished ||
                    ($payload['is_featured'] ?? 0) == 1 ||
                    ($payload['comment'] ?? 0) == 1;
                if ($needsPublishPermission
                    && !$this->isUserCan('system_content_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }

                // Нийтлэх төлөв өөрчлөгдсөн бол
                if ($isPublished && $record['published'] != 1) {
                    $payload['published_at'] = \date('Y-m-d H:i:s');
                    $payload['published_by'] = $this->getUserId();
                }

                // Model-д байхгүй болон аюултай талбаруудыг payload-оос салгах
                $files = \json_decode($payload['files'] ?? '{}', true) ?: [];
                unset($payload['files']);
                if (isset($payload['id'])) {
                    unset($payload['id']);
                }

                // Файлуудыг эхлээд боловсруулах
                $fileChanges = $this->processFiles($record, $files);

                // Өөрчлөлт байгаа эсэхийг шалгах
                $updates = [];
                foreach ($payload as $field => $value) {
                    if (($record[$field] ?? null) != $value) {
                        $updates[] = $field;
                    }
                }
                if (!empty($fileChanges)) {
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
                $context['file_changes'] = !empty($fileChanges) ? $fileChanges : 'files not changed';
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
            // basename filter (rawurldecode хийж уншигдахуйц нэр харуулна)
            $template->addFilter(new TwigFilter('basename', fn(string $path): string => \rawurldecode(\basename($path))));
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
     * Файлуудыг нэгдсэн аргаар боловсруулах.
     *
     * Frontend-ээс ирсэн files JSON-г parse хийж:
     * - Header image хадгалах/устгах
     * - Content media файлууд хадгалах
     * - Attachment файлууд нэмэх/шинэчлэх/устгах
     *
     * @param array $record  Бичлэг
     * @param array $files   Frontend-ээс ирсэн файлуудын мэдээлэл
     * @param bool $fromTemp Insert үйлдэл эсэх (temp folder-оос зөөх)
     * @return array Файлуудын өөрчлөлтүүдийн жагсаалт (хоосон бол өөрчлөлт байхгүй)
     */
    private function processFiles(array $record, array $files, bool $fromTemp = false): array
    {
        $changes = [];
        $userId = $this->getUserId();

        $model = new NewsModel($this->pdo);
        $table = $model->getName();

        $filesModel = new FilesModel($this->pdo);
        $filesModel->setTable($table);

        if ($fromTemp) {
            $this->setFolder("/$table/temp/$userId");
            $tempPath = $this->public_path;
            $tempFolder = $this->local_folder;
        }

        $this->setFolder("/$table/{$record['id']}");
        $recordPath = $this->public_path;
        $recordFolder = $this->local_folder;

        // Header image устгагдсан эсэх
        if (($files['headerImageRemoved'] ?? false) && !empty($record['photo'])) {
            $photoFilename = \basename(\rawurldecode($record['photo']));
            $this->unlinkByName($photoFilename);
            $record = $model->updateById($record['id'], ['photo' => '']);
            $changes[] = "header image deleted: $photoFilename";
        }

        // 1. Header Image - зөвхөн news.photo талбарт хадгална
        if (!empty($files['headerImage'])) {
            $headerData = $files['headerImage'];
            $filename = \basename($headerData['file']);

            if (!empty($record['photo'])) {
                $photoFilename = \basename(\rawurldecode($record['photo']));
                $this->unlinkByName($photoFilename);
            }

            if ($fromTemp) {
                $tempFile = "$tempFolder/$filename";
                if (\is_file($tempFile)) {
                    if (!\is_dir($recordFolder)) {
                        \mkdir($recordFolder, 0755, true);
                    }
                    \rename($tempFile, "$recordFolder/$filename");
                    $headerData['path'] = "$recordPath/" . \rawurlencode($filename);
                }
            }

            $model->updateById($record['id'], ['photo' => $headerData['path']]);
            $changes[] = "header image updated: $filename";
        }

        // 2. Content Media - DB-д бүртгэхгүй, зөвхөн файл зөөх
        foreach ($files['contentMedia'] ?? [] as $media) {
            if ($fromTemp) {
                $filename = \basename($media['file']);
                $tempFile = "$tempFolder/$filename";
                if (\is_file($tempFile)) {
                    if (!\is_dir($recordFolder)) {
                        \mkdir($recordFolder, 0755, true);
                    }
                    \rename($tempFile, "$recordFolder/$filename");
                    $changes[] = "content media moved: $filename";
                }
            }
        }

        // Content HTML дахь temp path-уудыг record path болгох
        if ($fromTemp) {
            $html = $record['content'] ?? '';
            if (\strpos($html, $tempPath) !== false) {
                $html = \str_replace($tempPath, $recordPath, $html);
                $model->updateById($record['id'], ['content' => $html]);
            }
        }

        // 3. Attachments - New
        foreach ($files['attachments']['new'] ?? [] as $att) {
            $filename = \basename($att['file']);

            if ($fromTemp) {
                $tempFile = "$tempFolder/$filename";
                if (\is_file($tempFile)) {
                    if (!\is_dir($recordFolder)) {
                        \mkdir($recordFolder, 0755, true);
                    }
                    \rename($tempFile, "$recordFolder/$filename");
                    $att['file'] = "$recordFolder/$filename";
                    $att['path'] = "$recordPath/" . \rawurlencode($filename);
                }
            }

            $filesModel->insert([
                'record_id'         => $record['id'],
                'file'              => $att['file'],
                'path'              => $att['path'],
                'size'              => $att['size'],
                'type'              => $att['type'],
                'mime_content_type' => $att['mime_content_type'],
                'description'       => $att['description'] ?? '',
                'created_by'        => $userId
            ]);
            $changes[] = "attachment added: $filename";
        }

        // 4. Attachments - Update existing (description only)
        $currentDescriptions = [];
        if (!empty($files['attachments']['existing'])) {
            $currentFiles = $filesModel->getRows(['WHERE' => "record_id={$record['id']} AND is_active=1"]);
            $currentDescriptions = \array_column($currentFiles, 'description', 'id');
        }
        foreach ($files['attachments']['existing'] ?? [] as $att) {
            $attId = (int)$att['id'];
            $newDesc = $att['description'] ?? '';
            if (($currentDescriptions[$attId] ?? '') !== $newDesc) {
                $filesModel->updateById($attId, [
                    'description' => $newDesc,
                    'updated_at'  => \date('Y-m-d H:i:s'),
                    'updated_by'  => $userId
                ]);
                $changes[] = "attachment description updated: #$attId";
            }
        }

        // 5. Attachments - Delete (soft delete)
        foreach ($files['attachments']['deleted'] ?? [] as $fileId) {
            $filesModel->deactivateById((int)$fileId, [
                'updated_at' => \date('Y-m-d H:i:s'),
                'updated_by' => $userId
            ]);
            $changes[] = "attachment deleted: #$fileId";
        }

        return $changes;
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
     * Мэдээ бүрийн хавсралт файлын тоог тоолох.
     *
     * @param string $table Хүснэгтийн нэр (жишээ: 'news')
     * @return array Мэдээний ID => {attach, files_size} бүтэцтэй массив
     */
    private function getFilesCounts(string $table): array
    {
        try {
            $sql =
                'SELECT record_id, COUNT(*) as cnt ' .
                "FROM {$table}_files " .
                'WHERE is_active=1 ' .
                'GROUP BY record_id';
            $result = $this->query($sql)->fetchAll();
            $counts = [];
            foreach ($result as $row) {
                $counts[$row['record_id']] = ['attach' => (int)$row['cnt']];
            }
            return $counts;
        } catch (\Throwable) {
            return [];
        }
    }
}
