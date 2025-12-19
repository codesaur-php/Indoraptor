<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

/**
 * Class LanguageController
 *
 * Нутагшуулалтын модулийн хэл удирдах (CRUD) үйлдлүүдийг хариуцсан контроллер.
 *
 * Энэ контроллер нь дараах боломжуудыг хангана:
 *  - Шинэ хэл үүсгэх (insert)
 *  - Хэлний мэдээлэл харах (view)
 *  - Хэлний мэдээлэл өөрчлөх (update)
 *  - Хэл идэвхгүй болгох (deactivate)
 *  - Нэг хэлнээс нөгөө хэл рүү localized content автоматаар хуулж өгөх
 *
 * Мөн аливаа үйлдэл бүр:
 *  - Эрх шалгаж эхэлдэг (RBAC)
 *  - SweetAlert / AJAX ашигласан UI-д хариу өгдөг
 *  - Logger түүх бичдэг
 *
 * @package Raptor\Localization
 */
class LanguageController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Хэл шинээр бүртгэх.
     *
     * GET → language-insert-modal.html (modal form)
     * POST → өгөгдөл шалгаад хэл үүсгэнэ
     *
     * Шаардлага:
     *  - Хэрэглэгч system_localization_insert эрхтэй байх
     *  - copy / code / locale / title талбарууд заавал байх
     *  - Хэлний код давхцахгүй байх
     *  - Locale болон нэр давхцахгүй байх
     *
     * Мөн:
     *  - Шинэ хэл амжилттай үүссэний дараа mother хэлнээс localized content хуулна
     *  - Үйл явдлыг logger-д бичнэ
     *
     * @return void JSON эсвэл modal render
     */
    public function insert()
    {
        try {
            // Хэрэглэгчийн эрх шалгах
            if (!$this->isUserCan('system_localization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            // POST → өгөгдөл боловсруулах
            if ($this->getRequest()->getMethod() == 'POST') {
                $payload = $this->getParsedBody();
                // Оролт шалгах
                if (empty($payload['copy'])
                    || empty($payload['code'])
                    || empty($payload['locale'])
                    || empty($payload['title'])
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-values'), 400);
                }

                // Localized контент хуулбарлах source хэлийг авах
                $model = new LanguageModel($this->pdo);
                $mother = $model->getRowWhere([
                    'code' => $payload['copy'],
                    'is_active' => 1
                ]);
                if (!isset($mother['code'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                unset($payload['copy']);

                // Давхардал шалгах
                $languages = $model->retrieve();
                foreach ($languages as $key => $value) {
                    if (
                        $payload['code'] == $key &&
                        $payload['locale'] == $value['locale'] &&
                        $payload['title'] == $value['title']
                    ) {
                        throw new \Exception($this->text('error-lang-existing'), 403);
                    }

                    if ($payload['code'] == $key || $payload['locale'] == $value['locale']) {
                        throw new \Exception($this->text('error-existing-lang-code'), 403);
                    }

                    if ($payload['title'] == $value['title']) {
                        throw new \Exception($this->text('error-lang-name-existing'), 403);
                    }
                }

                // Хэл үүсгэх
                $record = $model->insert(
                    $payload + ['created_by' => $this->getUserId()]
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);

                // Амжилттай үүссэн хэлний хувьд localized content мөрүүдийг хуулж үүсгэх
                $copied = $this->copyLocalizedContent($mother['code'], $payload['code']);
            } else {
                // GET → modal form рендерлэх
                $this->twigTemplate(__DIR__ . '/language-insert-modal.html')->render();
            }
        } catch (\Throwable $err) {
            // Алдааг POST → JSON, GET → modal хэлбэрээр
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // Лог бичих
            $context = ['action' => 'language-create'];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'Хэлний бичлэг үүсгэх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = 'Хэл [{record.code} - {record.title}] амжилттай үүслээ';
                $context += [
                    'id' => $record['id'],
                    'record' => $record,
                    "copied-localized-content-{$mother['code']}-to-{$record['code']}" => $copied
                ];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Хэл үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }

    /**
     * Хэлний мэдээллийг харах (modal).
     *
     * @param int $id Хүсэж буй хэлний дугаар
     *
     * Шаардлага:
     *  - Хэрэглэгч system_localization_index эрхтэй байх
     *
     * GET → language-retrieve-modal.html рендерлэнэ  
     *
     * Лог бүртгэл:
     *  - Амжилттай → NOTICE
     *  - Алдаа → ERROR
     */
    public function view(int $id)
    {
        try {
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new LanguageModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            $this->twigTemplate(
                __DIR__ . '/language-retrieve-modal.html',
                ['record' => $record]
            )->render();
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'language-view', 'id' => $id];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай хэлний мэдээлэл нээх үед алдаа гарлаа';
                $context += [
                    'error' => [
                        'code' => $err->getCode(),
                        'message' => $err->getMessage()
                    ]
                ];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.title} хэлний мэдээллийг үзэж байна';
                $context += ['record' => $record];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }

    /**
     * Хэлний мэдээллийг засварлах.
     *
     * @param int $id Засварлах хэлний дугаар
     *
     * GET → update modal  
     * PUT → шинэчлэл хийх
     *
     * Шаардлага:
     *  - system_localization_update эрхтэй байх
     *  - code, title талбарууд хоосонгүй байх
     *  - Default хэлний default статуст өөрчлөлт хийхийг хориглоно
     *
     * Default хэлний статус өөрчлөгдвөл бусад өмнө байсан дугаар хэлийг буулгана.
     *
     * Лог:
     *  - PUT → INFO
     *  - GET → NOTICE
     *  - Алдаа → ERROR
     */
    public function update(int $id)
    {
        try {
            if (!$this->isUserCan('system_localization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new LanguageModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            if ($this->getRequest()->getMethod() == 'PUT') {
                $payload = $this->getParsedBody();
                if (empty($payload['code']) || empty($payload['title'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }

                // Аль талбар өөрчлөгдсөнийг тодорхойлох
                $updates = [];
                foreach ($payload as $field => $value) {
                    if ($record[$field] != $value) {
                        $updates[] = $field;
                    }
                }
                if (empty($updates)) {
                    throw new \InvalidArgumentException('No update!');
                }

                // Default хэл буулгахыг хориглох
                if ($record['is_default'] == 1
                   && ($payload['is_default'] ?? 1) == 0
                ) {
                    throw new \InvalidArgumentException('You can\'t change default language!');
                }

                // Шинэчлэх
                $updated = $model->updateById(
                    $id,
                    $payload + ['updated_by' => $this->getUserId()]
                );
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);

                // Default хэл бол бусдаас default-г буулгана
                if ($updated['is_default'] == 1) {
                    $model->exec(
                        "UPDATE {$model->getName()} " .
                        "SET is_default=0, updated_by={$updated['updated_by']}, updated_at={$model->quote($updated['updated_at'])} " .
                        "WHERE id<>{$updated['id']} AND is_default=1"
                    );
                }
            } else {
                $this->twigTemplate(
                    __DIR__ . '/language-update-modal.html',
                    ['record' => $record]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'language-update', 'id' => $id];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'Хэлний мэдээлэл шинэчлэх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '[{record.title}] хэл амжилттай шинэчлэгдлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '[{record.title}] хэлний мэдээллийг шинэчлэхээр нээв';
                $context += ['record' => $record];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }

    /**
     * Хэл идэвхгүй болгох (зөөлөн устгал).
     *
     * POST / DELETE → JSON хариутай ажиллана
     *
     * Шаардлага:
     *  - Хэрэглэгч system_localization_delete эрхтэй байх
     *  - Default хэл устгахыг хориглоно
     *
     * Лог:
     *  - Амжилттай → ALERT
     *  - Алдаа → ERROR
     *
     * @return void JSON
     */
    public function deactivate()
    {
        try {
            if (!$this->isUserCan('system_localization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }

            $payload = $this->getParsedBody();
            if (!isset($payload['id']) ||
                !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = (int)$payload['id'];

            $model = new LanguageModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            if ($record['is_default'] == 1) {
                throw new \Exception('Cannot remove default language!', 403);
            }

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
            $context = ['action' => 'language-deactivate'];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'Хэл идэвхгүй болгох үед алдаа гарлаа';
                $context += [
                    'error' => [
                        'code' => $err->getCode(),
                        'message' => $err->getMessage()
                    ]
                ];
            } else {
                $level = LogLevel::ALERT;
                $message = '{record.title} хэл дараах шалтгаанаар идэвхгүй боллоо: [{server_request.body.reason}]';
                $context += ['record' => $record];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }

    /**
     * Нэг хэл дээрх бүх localized content-ийг
     * шинэ хэл рүү автоматаар хуулж өгөх.
     *
     * Энэ нь:
     *  - *_content гэсэн нэртэй бүх хүснэгтүүдийг Localized model эсэхийг шалгана
     *  - parent_id + code бүтэцтэй мөрүүдийг хуулна
     *  - Хэрэв ижил parent_id + code (шинэ код) аль хэдийн байгаа бол алгасна
     *  - copy хийсний дараа parent table дээр updated_at / updated_by шинэчилж өгнө
     *
     * @param string $from Эх хэлний код (жишээ: en)
     * @param string $to   Хуулах шинэ хэлний код (жишээ: pl)
     *
     * @return array|false Амжилттай хуулсан хүснэгтүүдийн жагсаалт,
     *                     амжилтгүй бол false
     */
    private function copyLocalizedContent(string $from, string $to): array|false
    {
        try {
            // Хайх query: MySQL, PostgreSQL, эсвэл SQLite
            if ($this->getDriverName() == 'pgsql') {
                $query =
                    'SELECT tablename FROM pg_catalog.pg_tables ' .
                    "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like '%_content'";
            } elseif ($this->getDriverName() == 'sqlite') {
                // SQLite хувилбар
                $query = "SELECT name as tablename FROM sqlite_master WHERE type='table' AND name LIKE '%_content'";
            } else {
                $query = 'SHOW TABLES LIKE ' . $this->quote('%_content');
            }
            $stmt = $this->prepare($query);
            if (!$stmt->execute()) {
                throw new \Exception('There seems like no possibly localized content tables!');
            }

            $copied = [];
            // *content хүснэгт бүрийг шалгах
            while ($rows = $stmt->fetch()) {
                $contentTable = \current($rows);
                
                // Хүснэгтийн баганууд
                if ($this->getDriverName() == 'sqlite') {
                    // SQLite хувилбар
                    $query = $this->query("PRAGMA table_info($contentTable)");
                    $columns = $query->fetchAll();
                    $id = $parent_id = $code = false;
                    $field = $param = [];

                    // Багануудыг ангилах
                    foreach ($columns as $column) {
                        $name = $column['name'];
                        if ($name == 'id' && $column['pk'] == 1) {
                            $id = true;
                        } elseif ($name == 'parent_id') {
                            $parent_id = true;
                        } elseif ($name == 'code') {
                            $code = true;
                        } else {
                            $field[] = $name;
                            $param[] = ":$name";
                        }
                    }
                } else {
                    // MySQL/PostgreSQL хувилбар
                    $query = $this->query("SHOW COLUMNS FROM $contentTable");
                    $columns = $query->fetchAll();
                    $id = $parent_id = $code = false;
                    $field = $param = [];

                    // Багануудыг ангилах
                    foreach ($columns as $column) {
                        $name = $column['Field'];
                        if ($name == 'id' && $column['Extra'] == 'auto_increment') {
                            $id = true;
                        } elseif ($name == 'parent_id') {
                            $parent_id = true;
                        } elseif ($name == 'code') {
                            $code = true;
                        } else {
                            $field[] = $name;
                            $param[] = ":$name";
                        }
                    }
                }
                if (!$id || !$parent_id || !$code || empty($field)) {
                    // Localized table биш байна. Алгасяа
                    continue;
                }

                // Эх хүснэгт байна уу?
                $table = \substr($contentTable, 0, \strlen($contentTable) - 8);
                if (!$this->hasTable($table)) {
                    // Localized model биш  байна. Алгасяа
                    continue;
                }

                // parent table баганууд
                if ($this->getDriverName() == 'sqlite') {
                    // SQLite хувилбар
                    $table_query = $this->query("PRAGMA table_info($table)");
                    $table_columns = $table_query->fetchAll();
                    $update = false;
                    $primary = false;
                    $updates = [];
                    $update_arguments = [];
                    $by_account = $this->getUserId();
                    foreach ($table_columns as $column) {
                        $name = $column['name'];
                    if ($name == 'id') {
                        $primary = true;
                    } elseif ($name == 'updated_at') {
                        $updates[] = 'updated_at=:at';
                    } elseif ($name == 'updated_by' && !empty($by_account)) {
                        $updates[] = 'updated_by=:by';
                        $update_arguments = [':by' => $by_account];
                    }
                }
                } else {
                    // MySQL/PostgreSQL хувилбар
                    $table_query = $this->query("SHOW COLUMNS FROM $table");
                    $table_columns = $table_query->fetchAll();
                    $update = false;
                    $primary = false;
                    $updates = [];
                    $update_arguments = [];
                    $by_account = $this->getUserId();
                    foreach ($table_columns as $column) {
                        $name = $column['Field'];
                        if ($name == 'id') {
                            $primary = true;
                        } elseif ($name == 'updated_at') {
                            $updates[] = 'updated_at=:at';
                        } elseif ($name == 'updated_by' && !empty($by_account)) {
                            $updates[] = 'updated_by=:by';
                            $update_arguments = [':by' => $by_account];
                        }
                    }
                }

                if (!$primary) continue;

                // Parent update query
                if (!empty($updates)) {
                    $sets = \implode(', ', $updates);
                    $update = $this->prepare("UPDATE $table SET $sets WHERE id=:id");
                }
                
                // Copy хийх өгөгдлүүд
                $fields = \implode(', ', $field);
                $select = $this->prepare("SELECT parent_id, code, $fields FROM $contentTable WHERE code=:1");
                if (!$select->execute([':1' => $from])) {
                    continue;
                }

                $inserted = false;
                $params = \implode(', ', $param);
                // Мөр мөрөөр хуулна
                while ($row = $select->fetch()) {
                    // Аль хэдийн шинэ хэлний мөр байгаа эсэх
                    $existing = $this->prepare(
                        "SELECT id FROM $contentTable WHERE parent_id=:1 AND code=:2"
                    );
                    $parameters = [':1' => $row['parent_id'], ':2' => $to];
                    if ($existing->execute($parameters) && $existing->rowCount() > 0) {
                        continue;
                    }

                    // шинэ хэлний мөр хуулбарлан үүсгэе
                    $insert = $this->prepare(
                        "INSERT INTO $contentTable(parent_id, code, $fields) VALUES(:1, :2, $params)"
                    );
                    foreach ($field as $name) {
                        $parameters[":$name"] = $row[$name];
                    }
                    if ($insert->execute($parameters)) {
                        $inserted = true;

                        // Parent таблицын updated талбарууд шинэчлэх
                        if ($update) {
                            $update_arguments[':id'] = $row['parent_id'];
                            $update_arguments[':at'] = \date('Y-m-d H:i:s');
                            $update->execute($update_arguments);
                        }
                    }
                }

                // Хуулсан хүснэгтүүдийн нэрс жагсаалтруу нэмэх
                if ($inserted) {
                    $copied[$table] = $contentTable;
                }
            }
            return $copied;
        } catch (\Exception $ex) {
            // Алдаа гарсан тул лог бичээд false буцаана
            $this->errorLog($ex);
            return false;
        }
    }
}
