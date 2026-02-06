<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;
use Twig\TwigFilter;

/**
 * Class PagesController
 *
 * Хуудас (Pages) модулийн Dashboard Controller.
 * Хуудас үүсгэх, засварлах, унших, харах, идэвхгүй болгох
 * зэрэг бүх CRUD үйлдлийг гүйцэтгэнэ.
 *
 * Онцлог:
 *  - Хуудас бүр нэг хэлтэй (code), parent_id-р шатлал үүсгэнэ
 *  - Header image (photo) + content media + attachment файлуудтай
 *  - Published/unpublished төлөвтэй, нийтлэхэд тусгай эрх шаардлагатай
 *  - Slug автоматаар үүсгэдэг (PagesModel)
 *
 * @package Raptor\Content
 */
class PagesController extends FileController
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Хуудасны жагсаалтын Dashboard хуудас.
     *
     * - Шүүлтүүр: хэл (code), төрөл (type), ангилал (category), нийтлэгдсэн эсэх (published)
     * - pages-index.html template-д filter утгуудыг дамжуулна
     *
     * Permission: system_content_index
     */
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $filters = [];
        // pages хүснэгтийн нэрийг PagesModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
        $table = (new PagesModel($this->pdo))->getName();
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
        $dashboard = $this->twigDashboard(__DIR__ . '/pages-index.html', ['filters' => $filters]);
        $dashboard->set('title', $this->text('pages'));
        $dashboard->render();

        $this->indolog($table, LogLevel::NOTICE, 'Хуудас жагсаалтыг үзэж байна', ['action' => 'index']);
    }

    /**
     * Хуудасны жагсаалтыг JSON хэлбэрээр буцаана.
     *
     * Query параметрүүдээр шүүлтүүр хийх боломжтой:
     * code, type, category, published, is_active
     *
     * Permission: system_content_index
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
            // pages хүснэгтийн нэрийг PagesModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
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
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }

    /**
     * Шинэ хуудас үүсгэх.
     *
     * - GET: Хуудас үүсгэх форм харуулна
     * - POST: Хуудас үүсгэж DB-д хадгална
     *   - Header image, content media, attachment файлууд temp folder-оос зөөгдөнө
     *   - published=1 бол system_content_publish эрх шаардлагатай
     *
     * Permission: system_content_insert
     */
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new PagesModel($this->pdo);
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
                    __DIR__ . '/page-insert.html',
                    [
                        'table' => $table,
                        'infos' => $this->getInfos($table),
                        'max_file_size' => $this->getMaximumFileUploadSize()
                    ]
                );
                $dashboard->set('title', $this->text('add-record') . ' | Pages');
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
                $message = 'Хуудас үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.title}] хуудсыг амжилттай үүсгэлээ';
                $context += ['record_id' => $id, 'record' => $record];
                $context['file_changes'] = !empty($fileChanges) ? $fileChanges : 'files not changed';
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Хуудас үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog($table ?? 'pages', $level, $message, $context);
        }
    }

    /**
     * Хуудсыг blog хэлбэрээр унших (read view).
     *
     * - Хуудасны контент + хавсралт файлуудыг харуулна
     * - read_count тоолуурыг нэмэгдүүлнэ
     *
     * Permission: system_content_index
     *
     * @param int $id Хуудасны ID
     */
    public function read(int $id)
    {
        try {
            $model = new PagesModel($this->pdo);
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

            $template = $this->twigTemplate(__DIR__ . '/page-read.html');
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $template->set($key, $value);
            }
            $template->set('record', $record);
            $template->set('files', $files);
            $template->addFilter(new TwigFilter('basename', fn(string $path): string => \rawurldecode(\basename($path))));
            $template->render();
            $model->updateById($id, ['read_count' => $record['read_count'] + 1]);
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'read', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хуудсыг унших үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record_id} дугаартай [{title}] хуудсыг уншиж байна';
                $context += $record + ['files' => $files];
            }
            $this->indolog($table ?? 'pages', $level, $message, $context);
        }
    }

    /**
     * Хуудасны дэлгэрэнгүй мэдээлэл харах (Dashboard view).
     *
     * - Бичлэг + хавсралт файлууд + эцэг хуудасны мэдээлэл
     *
     * Permission: system_content_index
     *
     * @param int $id Хуудасны ID
     */
    public function view(int $id)
    {
        try {
            $model = new PagesModel($this->pdo);
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
            $infos = $this->getInfos($table, "(id=$id OR id={$record['parent_id']})");
            $dashboard = $this->twigDashboard(
                __DIR__ . '/page-view.html',
                [
                    'table' => $table,
                    'record' => $record,
                    'files' => $files,
                    'infos' => $infos
                ]
            );
            $dashboard->set('title', $this->text('view-record') . ' | Pages');
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'view', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хуудасны мэдээллийг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.title}] хуудасны мэдээллийг үзэж байна';
                $context += ['record' => $record, 'files' => $files];
            }
            $this->indolog($table ?? 'pages', $level, $message, $context);
        }
    }

    /**
     * Хуудасны бичлэгийг шинэчлэх.
     *
     * - GET: Засварлах форм харуулна
     * - PUT: Бичлэгийг шинэчлэнэ
     *   - Гол зураг (photo) шинэчлэх/устгах боломжтой
     *   - Хавсаргасан файлууд нэмэх/засах/устгах боломжтой
     *   - published төлөв өөрчлөхөд system_content_publish эрх шаардлагатай
     *
     * Permission: system_content_update
     *
     * @param int $id Шинэчлэх хуудасны ID
     */
    public function update(int $id)
    {
        try {
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new PagesModel($this->pdo);
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
                $infos = $this->getInfos($table, "id!=$id AND parent_id!=$id");
                $files = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
                $dashboard = $this->twigDashboard(
                    __DIR__ . '/page-update.html',
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
                $message = '{record_id} дугаартай хуудасны мэдээллийг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.title}] хуудасны мэдээллийг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
                $context['file_changes'] = !empty($fileChanges) ? $fileChanges : 'files not changed';
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.title}] хуудасны мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record, 'files' => $files];
            }
            $this->indolog($table ?? 'pages', $level, $message, $context);
        }
    }

    /**
     * Хуудсын бичлэгийг идэвхгүй болгоно (soft delete).
     *
     * Бодит файл устахгүй, is_active=0 болгоно.
     *
     * Permission: system_content_delete
     */
    public function deactivate()
    {
        try {
            $model = new PagesModel($this->pdo);
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
                $message = 'Хуудсыг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{record_id} дугаартай [{server_request.body.title}] хуудсыг идэвхгүй болголоо';
                $context += ['record_id' => $id];
            }
            $this->indolog($table ?? 'pages', $level, $message, $context);
        }
    }

    /**
     * Хуудасны шатлалтай мэдээллийг авах.
     *
     * Хуудас бүрийн parent_id-г дагаж parent_titles
     * (жишээ: "Нүүр » Бидний тухай » ") бүрдүүлнэ.
     *
     * @param string $table  Хүснэгтийн нэр
     * @param string $condition  Нэмэлт WHERE нөхцөл (хоосон бол бүгдийг авна)
     * @return array  id => [id, parent_id, title, parent_titles] бүтэцтэй массив
     */
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
        } catch (\Throwable) {}

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

    /**
     * Хуудасны өвөг эцгийн шатлалыг рекурсивээр олох.
     *
     * @param int   $id       Хуудасны ID
     * @param array $pages    Бүх хуудасны массив (id => row)
     * @param array $ancestry Өвөг эцгийн жагсаалт (reference)
     * @return array parent_id => depth бүтэцтэй массив
     */
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

    /**
     * Хуудас бүрийн хавсралт файлын тоог тоолох.
     *
     * @param string $table Хүснэгтийн нэр
     * @return array record_id => ['attach' => count] бүтэцтэй массив
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

        $model = new PagesModel($this->pdo);
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

        // 1. Header Image - зөвхөн pages.photo талбарт хадгална
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
}
