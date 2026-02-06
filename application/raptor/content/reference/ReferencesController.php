<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

/**
 * Class ReferencesController
 *
 * Лавлах төрлийн контентуудыг (reference tables) харах, үүсгэх,
 * засварлах, идэвхгүй болгох зэрэг CRUD үйлдлүүдийг
 * хариуцдаг Indoraptor Dashboard Controller.
 *
 * ReferenceModel нь олон хэлний (localized) контент удирдах чадвартай
 * тул энэхүү controller нь үр дүнд ашиглагдах бүх хэл дээрх title/content
 * утгуудыг нэг дор хүлээн авч хадгалдаг.
 *
 * Гол ажиллагаа:
 * ---------------
 * 1) reference_* болон reference_*_content хүснэгтүүдийг илрүүлж, жагсаана
 * 2) ReferenceInitial дахь seed функцуудыг ашиглан хүснэгт байхгүй бол
 *      ReferenceModel::__initial() → хүснэгт үүсгэнэ → seed өгөгдөл оруулна
 * 3) Лавлах контентуудыг жагсаах, харах, үүсгэх, шинэчлэх, идэвхгүй болгох
 * 4) Бүх үйлдлийг indolog() ашиглан системийн лог-д тэмдэглэнэ
 *
 * Permission:
 * -----------
 * - system_content_index
 * - system_content_insert
 * - system_content_update
 * - system_content_delete
 *
 * @package Raptor\Content
 */
class ReferencesController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    /**
     * Лавлах хүснэгтүүдийн жагсаалт (reference tables) болон тэдгээрт
     * хадгалагдсан бичлэгүүдийг админ Dashboard дээр харуулна.
     *
     * @return void ReferenceModel + ReferenceInitial дээр үндэслэн лавлах хүснэгтүүдийг админд харагдуулна
     */
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        if ($this->getDriverName() == 'pgsql') {
            $query = 
                'SELECT tablename FROM pg_catalog.pg_tables ' .
                "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like 'reference_%'";
        } elseif ($this->getDriverName() == 'sqlite') {
            // SQLite хувилбар
            $query = "SELECT name as tablename FROM sqlite_master WHERE type='table' AND name LIKE 'reference_%'";
        } else {
            $query = 'SHOW TABLES LIKE ' . $this->quote('reference_%');
        }
        $reference_likes = $this->query($query)->fetchAll();
        $names = [];
        foreach ($reference_likes as $name) {
            $names[] = \reset($name);
        }
        $references = [];
        foreach ($names as $name) {
            if (\in_array($name . '_content', $names)) {
                $references[] = \substr($name, \strlen('reference_'));
            }
        }
        $initials = \get_class_methods(ReferenceInitial::class);
        foreach ($initials as $value) {
            $initial = \substr($value, \strlen('reference_'));
            if (!empty($initial) && !\in_array($initial, $references)) {
                $references[] = $initial;
            }
        }
        $tables = ['templates' => []];
        foreach ($references as $reference) {
            if (!\in_array($reference, \array_keys($tables))) {
                $tables[$reference] = [];
            }
        }
        foreach (\array_keys($tables) as $table) {
            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table);
            $tables[$table] = $reference->getRows(['WHERE' => 'p.is_active=1']);
        }
        $dashboard = $this->twigDashboard(__DIR__ . '/references-index.html', ['tables' => $tables]);
        $dashboard->set('title', $this->text('reference-tables'));
        $dashboard->render();
        
        $this->indolog('content', LogLevel::NOTICE, 'Лавлах хүснэгтүүдийн жагсаалтыг үзэж байна', ['action' => 'reference-index']);
    }
    
    /**
     * reference_{table} хүснэгтэд шинээр бичлэг (reference record) нэмэх (INSERT).
     *
     * Ажиллах зарчим:
     * ---------------
     * 1) POST бол:
     *      - payload + олон хэлний контентийг задлаж ангилна
     *      - хүснэгт баазад (DB) байгаа эсэх/эсвэл ReferenceInitial дотор method байдгийг шалгана
     *      - ReferenceModel::insert() → бичлэг хадгална
     *      - JSON хариу буцаана
     *
     * 2) GET бол:
     *      - шинээр бичлэг нэмэх форм бүхий dashboard template рендерлэнэ
     *
     * Анхаарах онцлогууд:
     * ------------------
     * - reference_{table} хүснэгт баазад байхгүй байж болно!
     *   Энэ тохиолдолд ReferenceModel → __initial() → хүснэгт үүсгэнэ.
     *
     * @param string $table  reference_ дараах хүснэгтийн нэр
     * @return void
     */
    public function insert(string $table)
    {
        try {
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $payload = [];
                $content = [];
                $parsedBody = $this->getParsedBody();
                foreach ($parsedBody as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                        }
                    } else {
                        $payload[$index] = $value;
                    }
                }                
                if (empty($parsedBody['keyword'])
                    || empty($parsedBody['category'])
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-values'), 400);
                }
                
                $initials = \get_class_methods(ReferenceInitial::class);
                if (!$this->hasTable("reference_$table")
                    && !\in_array("reference_$table", $initials)
                ) {
                    throw new \Exception("Table reference_$table not found!", 404);
                }
                
                $reference = new ReferenceModel($this->pdo);
                $reference->setTable($table);
                $record = $reference->insert(
                    $payload + ['created_by' => $this->getUserId()], $content
                );
                if (!isset($record['id'])) {
                    throw new \Exception($this->text('record-insert-error'));
                }                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                $dashboard = $this->twigDashboard(
                    __DIR__ . '/reference-insert.html',
                    ['table' => $table]
                );
                $dashboard->set('title', $this->text('add-record') . ' | ' . \ucfirst($table));
                $dashboard->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = [
                'action' => 'reference-create',
                'table' => $table
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Лавлах мэдээллийг [{table}] хүснэгтэд үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = '[{record.keyword}] түлхүүртэй лавлах мэдээллийг [{table}] хүснэгт дээр амжилттай үүсгэлээ';
                $context += ['id' => $record['id'], 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Лавлах мэдээллийг {table} хүснэгт дээр үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    /**
     * Лавлах контентийн дэлгэрэнгүй мэдээллийг харах (VIEW).
     *
     * Процесс:
     * --------
     * 1) Хүснэгт байгаа эсэхийг шалгана
     * 2) p.id = $id ба p.is_active = 1 нөхцөлөөр бичлэгийг уншина
     * 3) Dashboard-ийг render хийнэ
     *
     * Хэрэглээ:
     * ---------
     * Админ хэсэгт view button дарсан үед ашиглагдана.
     *
     * @param string $table  reference_ дараах хүснэгтийн нэр
     * @param int    $id     Бичлэгийн ID
     * @return void
     */
    public function view(string $table, int $id)
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            } elseif (!$this->hasTable("reference_$table")) {
                throw new \Exception("Table reference_$table not found", 404);
            }

            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table);
            $record = $reference->getRowWhere([
                'p.id' => $id,
                'p.is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $dashboard = $this->twigDashboard(
                __DIR__ . '/reference-view.html',
                ['table' => $table, 'record' => $record]
            );
            $dashboard->set('title', $this->text('view-record') . ' | ' . \ucfirst($table));
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = [
                'action' => 'reference-view',
                'table' => $table,
                'id' => $id
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{table} хүснэгтээс {id} дугаартай лавлах мэдээллийг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгтийн {record.id} дугаартай [{record.keyword}] түлхүүртэй лавлах мэдээллийг үзэж байна';
                $context += ['record' => $record];
            }
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    /**
     * Лавлах бичлэгийг шинэчлэх (UDPATE).
     *
     * Ажиллах зарчим:
     * ---------------
     * 1) Бичлэг байгаа эсэхийг шалгах
     * 2) PUT хүсэлт ирсэн бол:
     *      - Payload болон олон хэлний content-ийг салгаж ангилна
     *      - Өөрчлөгдсөн талбаруудыг updates[] дотор бүртгэнэ
     *      - ReferenceModel::updateById() ашиглан шинэчилнэ
     *      - JSON хариу буцаана
     *
     * 3) GET бол:
     *      - Update form бүхий dashboard template-ийг render хийнэ
     *
     * Онцгой тохиолдол:
     * -----------------
     * - Хүсэлтэд ямар ч өөрчлөгдсөн утга агуулаагүй байвал "No update!" алдаа өгнө.
     *
     * @param string $table  reference_ дараах хүснэгтийн нэр
     * @param int    $id     Бичлэгийн ID
     * @return void
     */
    public function update(string $table, int $id)
    {
        try {
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            } elseif (!$this->hasTable("reference_$table")) {
                throw new \Exception("Table reference_$table not found", 404);
            }
            
            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table);
            $record = $reference->getRowWhere([
                'p.id' => $id,
                'p.is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'), 400);
            }
            if ($this->getRequest()->getMethod() == 'PUT') {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody)) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload = [];
                $content = [];
                $updates = [];
                foreach ($parsedBody as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                            if ($record['localized'][$key][$index] != $value) {
                                $updates[] = "{$index}_{$key}";
                            }
                        }
                    } else {
                        $payload[$index] = $value;
                        if ($record[$index] != $value) {
                            $updates[] = $index;
                        }
                    }
                }
                if (empty($updates)) {
                    throw new \InvalidArgumentException('No update!');
                }
                $updated = $reference->updateById(
                    $id, $payload + ['updated_by' => $this->getUserId()], $content
                );
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                $dashboard = $this->twigDashboard(
                    __DIR__ . '/reference-update.html',
                    ['table' => $table, 'record' => $record]
                );
                $dashboard->set('title', $this->text('edit-record') . ' | ' . \ucfirst($table));
                $dashboard->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = [
                'action' => 'reference-update',
                'table' => $table,
                'id' => $id
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{table} хүснэгтийн {id} дугаартай лавлах мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '{table} хүснэгтийн {record.id} дугаартай [{record.keyword}] түлхүүртэй лавлах мэдээллийг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгтийн {record.id} дугаартай [{record.keyword}] түлхүүртэй лавлах мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record];
            }
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    /**
     * Лавлах мэдээллийг идэвхгүй болгох (SOFT DELETE).
     *
     * ReferenceModel::deactivateById() функц ашиглан:
     *  - is_active = 0
     *  - updated_by = current user $id
     *  - updated_at = now()
     *
     * Үр дүн:
     * -------
     * - Dashboard JSON success/error буцаана
     * - Лог-д бичигдэнэ
     *
     * Анхаарах зүйл:
     * --------------
     * - Table нэрийг хэрэглэгчийн илгээсэн утгаас шалгаж цэвэрлэнэ
     * - reference_{table} байхгүй бол 404 алдаа
     *
     * @return void
     */
    public function deactivate()
    {
        try {
            if (!$this->isUserCan('system_content_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || empty($payload['table'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            } elseif (!$this->hasTable('reference_' . \preg_replace('/[^A-Za-z0-9_-]/', '', $payload['table']))) {
                throw new \InvalidArgumentException("Table reference_{$payload['table']} not found", 404);
            }
            
            $table = $payload['table'];
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table);
            $deactivated = $reference->deactivateById(
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
            $context = ['action' => 'reference-deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Лавлах мэдээлэл идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{table} хүснэгтээс {id} дугаартай [{server_request.body.keyword}] түлхүүртэй лавлах мэдээллийг идэвхгүй болголоо';
                $context += ['table' => $table, 'id' => $id];
            }
            $this->indolog('content', $level, $message, $context);
        }
    }
}
