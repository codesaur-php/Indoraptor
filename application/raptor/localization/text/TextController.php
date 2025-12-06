<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

/**
 * Class TextController
 *
 * Нутагшуулалтын (Localization) системийн орчуулгын текстүүдийг
 * үүсгэх, үзэх, засварлах болон идэвхгүй болгох CRUD ажиллагааг
 * хариуцдаг Controller класс.
 *
 * Энэ контроллер нь TextModel, LocalizedModel, TextInitial зэрэг
 * өгөгдлийн түвшний объектуудтай хамтран ажиллаж, олон хэл дээрх
 * текстүүдийг нэг түлхүүрийн (keyword) дор удирдах боломжийг бүрдүүлдэг.
 *
 * Үндсэн үүргүүд:
 * ---------------
 *
 * 1) insert(string $table)
 *    - Шинэ орчуулгын текст үүсгэнэ.
 *    - Payload болон олон хэлний контентыг задлан payload + content хэлбэрт хөрвүүлнэ.
 *    - TextInitial болон өгөгдлийн сантай тулган тухайн хүснэгт хүчинтэй эсэхийг шалгана.
 *    - Түлхүүр үг давхцаж буй эсэхийг бүх localization_text_* хүснэгтүүдээс шалгана.
 *    - Амжилттай бол JSON хариу буцаана.
 *
 * 2) view(string $table, int $id)
 *    - Тухайн орчуулгын текстийн мэдээллийг (keyword + олон хэлний текст)
 *      харах зориулалттай modal template руу өгөгдөл дамжуулна.
 *
 * 3) update(string $table, int $id)
 *    - Одоогийн бичлэг болон олон хэлний контентуудыг харьцуулж,
 *      зөвхөн өөрчлөгдсөн талбаруудыг update хийнэ.
 *    - Keyword давхцсан эсэхийг бүх орчуулгын хүснэгтүүдээс дахин шалгана.
 *    - Амжилттай бол JSON хариу буцаана.
 *
 * 4) deactivate()
 *    - Орчуулгын текстийг is_active=0 болгож идэвхгүй болгоно.
 *    - Тухайн текстийг ямар хүснэгтэд байрлаж байгааг payload-аас тодорхойлно.
 *
 * 5) getTextTableNames()
 *    - Өгөгдлийн санд байгаа localization_text_*_content хүснэгтүүдийг илрүүлнэ.
 *    - Мөн TextInitial::localization_text_{table} хэлбэрийн бүх функцүүдийг уншиж,
 *      seed нь хоосон байсан ч тухайн хүснэгт системд бүртгэлтэй гэж үзнэ.
 *    - Энэ нь LocalizationController болон UI-д бүх орчуулгын модулиудыг харагдуулах үндсэн механизм юм.
 *
 * 6) findByKeyword()
 *    - Өгөгдсөн бүх орчуулгын хүснэгт дунд keyword давхцаж байгаа эсэхийг шалгана.
 *    - Энэ нь систем дотор keyword-н давхцалыг зөвшөөрөхгүй байх зорилготой.
 *
 * Архитектурын онцлог:
 * ---------------------
 * • Нэг keyword олон хэлний тексттэй байх бөгөөд тэдгээр нь
 *   TextModel => LocalizedModel механизмаар хадгалагдана.
 *
 * • TextInitial доторх seed функцүүд нь хоосон байсан ч,
 *   тэдгээрийн нэр (localization_text_xxx) нь орчуулгын модуль
 *   системд оршин буй гэсэн утгатай тул localization dashboard UI-д харагдана.
 *
 * • CRUD бүх ажиллагаа нь indolog() ашиглан localization протоколд
 *   бичигдэх бөгөөд аудит болон лог хөтлөлтийн бүрэн дэмжлэгтэй.
 *
 * • Permission шалгалт нь Raptor RBAC системийн system_localization_* эрхүүдийг ашиглана.
 *
 * Энэ контроллер нь Indoraptor CMS-ийн бүх модулиудын орчуулгын
 * текстийг цэгцтэй, өргөтгөх боломжтой байдлаар удирдах төв цэг
 * болж ажилладаг.
 */
class TextController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    /**
     * Орчуулгын текстийн шинэ бичлэг үүсгэх (INSERT).
     *
     * @param string $table
     *      TextModel-ийн орчуулгын хүснэгтийн нэр.
     *      Жишээ: "default", "dashboard", "user" гэх мэт.
     *
     * Ажиллах зарчим:
     * ---------------
     * 1. Хэрэглэгчийн эрхийг шалгана (system_localization_insert).
     * 2. Хэрэв POST хүсэлт ирсэн бол:
     *      - Формаас ирсэн өгөгдлийг payload (гол мэдээлэл) ба content (олон хэлний текст) хэлбэрээр ялгана.
     *      - $table хүснэгт системд хүчинтэй эсэхийг шалгана.
     *      - keyword бусад бүх localization_text_* хүснэгтэд давхцаж буй эсэхийг findByKeyword() ашиглан шалгана.
     *      - TextModel→setTable() дуудаж тухайн хүснэгтийг онооно.
     *      - insert() ажиллуулж шинэ текст бүртгэнэ.
     *      - Амжилттай тохиолдолд JSON хариу хэвлэнэ.
     * 3. Хэрэв GET хүсэлт бол text-insert-modal.html template руу өгөгдөл дамжуулна.
     *
     * Алдаа:
     * ------
     * • Зөвшөөрөлгүй бол 401
     * • invalid-request бол 400
     * • keyword давхцсан бол Exception
     * • insert амжилтгүй бол record-insert-error
     *
     * Лог:
     * ----
     * Үйлдлийн төгсгөлд indolog() ашиглан localization протоколд log бичигдэнэ.
     */
    public function insert(string $table)
    {
        try {
            if (!$this->isUserCan('system_localization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            if ($this->getRequest()->getMethod() == 'POST') {
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
                
                $tables = $this->getTextTableNames();
                if (empty($payload['keyword'])
                    || !\in_array($table, $tables)
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                $found = $this->findByKeyword($tables, $parsedBody['keyword']);
                if (isset($found['id'])
                    && !empty($found['table'])
                ) {
                    throw new \Exception(
                        $this->text('keyword-existing-in') . ' -> ID = ' .
                        $found['id'] . ', Table = ' . $found['table']
                    );
                }
                
                $model = new TextModel($this->pdo);
                $model->setTable($table);
                $record = $model->insert(
                    $payload + ['created_by' => $this->getUserId()], $content
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                $this->twigTemplate(
                    __DIR__ . '/text-insert-modal.html',
                    ['table' => $table]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'text-create', 'table' => $table];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{table} хүснэгт дээр текст үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = '{table} хүснэгт дээр [{record.keyword}] текст амжилттай үүслээ';
                $context += ['id' => $record['id'], 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгт дээр текст үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    /**
     * Орчуулгын текстийн мэдээллийг харах (VIEW).
     *
     * @param string $table
     *      Харах гэж буй текст байрлаж буй орчуулгын хүснэгтийн нэр.
     *
     * @param int $id
     *      Тухайн текстийн хүснэгт дээрх id дугаар.
     *
     * Ажиллах зарчим:
     * ----------------
     * 1. Хэрэглэгчийн эрх (system_localization_index) шалгана.
     * 2. Хүснэгтийн нэр $table нь:
     *      - өгөгдлийн сангийн хүснэгтүүд болон
     *      - TextInitial дотор байгаа seed функцүүдийн нэртэй таарч буй эсэхийг
     *        getTextTableNames() ашиглан тодорхойлно.
     * 3. TextModel→setTable() → getRowWhere() ашиглаж тухайн id-тэй бичлэгийг уншина.
     * 4. Олдвол text-retrieve-modal.html template рүү дамжуулан modal хэлбэрээр харуулна.
     *
     * Алдаа:
     * -------
     * • table хүчинтэй биш → invalid-request
     * • бичлэг олдоогүй → no-record-selected
     *
     */
    public function view(string $table, int $id)
    {
        try {
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $tables = $this->getTextTableNames();
            if (!\in_array($table, $tables)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            
            $model = new TextModel($this->pdo);
            $model->setTable($table);
            $record = $model->getRowWhere([
                'p.id' => $id,
                'p.is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $this->twigTemplate(
                __DIR__ . '/text-retrieve-modal.html',
                ['table' => $table, 'record' => $record]
            )->render();
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = [
                'action' => 'text-view',
                'table' => $table,
                'id' => $id
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{table} хүснэгтийн {id} дугаартай текст мэдээллийг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгтээс [{record.keyword}] текст мэдээллийг үзэж байна';
                $context += ['record' => $record];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    /**
     * Орчуулгын текстийн бичлэгийг засварлах (UPDATE).
     *
     * @param string $table
     *      Орчуулгын модуль/хүснэгтийн нэр (default, dashboard, user, ...).
     *
     * @param int $id
     *      Засварлах гэж буй бичлэгийн хүснэгт дээрх мөрийн дугаар.
     *
     * Ажиллах зарчим:
     * ----------------
     * 1. Хэрэглэгчийн update эрх (system_localization_update) шалгана.
     * 2. $table хүчинтэй эсэхийг getTextTableNames() ашиглан нотолно.
     * 3. TextModel→setTable(), getRowWhere() ашиглан одоогийн бичлэгийг уншина.
     * 4. Хэрэв PUT хүсэлт бол:
     *      - payload ба content-г ялган ангилна.
     *      - Өөрчлөгдсөн талбаруудыг (updates[]) жагсаана.
     *      - keyword давхцсан эсэхийг findByKeyword() ашиглан шалгана.
     *      - updateById() ажиллуулж олон хэлний текстүүдийг шинэчилнэ.
     *      - JSON хариу хэвлэнэ.
     * 5. Хэрэв GET хүсэлт бол text-update-modal.html template рүү дамжуулна.
     *
     * Алдаа:
     * -------
     * • Хэрэглэгч update эрхгүй бол → 401
     * • table хүчинтэй биш бол → invalid-request
     * • бичлэг олдоогүй бол → no-record-selected
     * • өөрчлөлт хийгдээгүй бол → Exception("No update!")
     * • keyword өөр хүснэгтэд давхцсан бол → keyword-existing-in
     *
     * Лог:
     * ----
     *   - update амжилттай бол: `{table} хүснэгтийн [keyword] текст мэдээллийг амжилттай шинэчлэлээ`
     *   - modal нээгдэж байгаа үед: `... шинэчлэхээр нээж байна`
     *   - алдаа үед: `... өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо`
     */
    public function update(string $table, int $id)
    {
        try {
            if (!$this->isUserCan('system_localization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }            
            $tables = $this->getTextTableNames();
            if (!\in_array($table, $tables)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }            
            $model = new TextModel($this->pdo);
            $model->setTable($table);
            $record = $model->getRowWhere([
                'p.id' => $id,
                'p.is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
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
                            if ($record['localized'][$index][$key] != $value) {
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
                if (empty($payload['keyword'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $found = $this->findByKeyword($tables, $parsedBody['keyword']);
                if (isset($found['table']) && isset($found['id'])
                    && ($found['id'] != $id || $found['table'] != $table)
                ) {
                    throw new \Exception(
                        $this->text('keyword-existing-in') . ' -> ID = ' .
                        $found['id'] . ', Table = ' . $found['table']);
                }
                $updated = $model->updateById(
                    $id, $payload + ['updated_by' => $this->getUserId()], $content
                );
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }                
                $this->respondJSON([
                    'type' => 'primary',
                    'status' => 'success',
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                $this->twigTemplate(
                    __DIR__ . '/text-update-modal.html',
                    ['record' => $record, 'table' => $table]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = [
                'action' => 'text-update',
                'table' => $table,
                'id' => $id
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{table} хүснэгтээс {id} дугаартай текст мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '{table} хүснэгтийн [{record.keyword}] текст мэдээллийг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{table} хүснэгтээс [{record.keyword}] текст мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    /**
     * Орчуулгын текст мэдээллийг идэвхгүй болгох (SOFT DELETE).
     *
     * Payload бүтэц:
     * --------------
     * [
     *     'table' => 'default',
     *     'id'    => <int>
     * ]
     *
     * Ажиллах зарчим:
     * ----------------
     * 1. Хэрэглэгч delete эрхтэй эсэхийг шалгана (system_localization_delete).
     * 2. table нэр хүчинтэй эсэхийг getTextTableNames() ашиглан шалгана.
     * 3. id дугаар хүчинтэй тоо эсэхийг FILTER_VALIDATE_INT ашиглан баталгаажуулна.
     * 4. TextModel→setTable() → deactivateById() ашиглаж тухайн бичлэгийг:
     *        is_active = 0
     *        updated_by = current_user
     *        updated_at = now()
     *    болгон хадгална.
     * 5. Амжилттай бол JSON хэлбэрээр success хариу буцаана.
     *
     * Алдаа:
     * -------
     * • Эрхгүй → 401
     * • invalid-request → table эсвэл id буруу
     * • deactivation амжилтгүй → no-record-selected
     *
     * Лог:
     * ----
     *   - Амжилттай үед: `{table} хүснэгтээс {id} дугаартай [keyword] текст идэвхгүй болгов`
     *   - Алдаа үед: `... идэвхгүй болгох үед алдаа гарч зогслоо`
     */
    public function deactivate()
    {
        try {
            if (!$this->isUserCan('system_localization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            $payload = $this->getParsedBody();
            $tables = $this->getTextTableNames();
            if (empty($payload['table'])
                || !\in_array($payload['table'], $tables)
                || !isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $model = new TextModel($this->pdo);
            $model->setTable($payload['table']);
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
            $context = ['action' => 'text-deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Текст мэдээлэл идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{table} хүснэгтээс {id} дугаартай [{server_request.body.keyword}] текст мэдээллийг идэвхгүй болголоо';
                $context += ['table' => $payload['table'], 'id' => $id];
            }
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    /**
     * Системд байгаа бүх орчуулгын текстийн хүснэгтийн нэрсийг автоматаар илрүүлэх.
     *
     * Буцаах утга:
     *      ["default", "dashboard", "user", "shop", ...]
     *
     * Ажиллах зарчим:
     * ----------------
     * 1. Өгөгдлийн сан дахь бүх localization_text_*_content хүснэгтийг илрүүлнэ.
     * 2. Таblename-аа “localization_text_” болон “_content” хэсгийг тайруулж модуль нэр гаргана.
     * 3. TextInitial::class доторх бүх static функцийн нэрсийг уншина:
     *      localization_text_user
     *      localization_text_default
     *      localization_text_dashboard
     *      ...
     *      Эдгээр нь seed хоосон байсан ч системд оршин буй модуль гэж үзнэ.
     * 4. Хэрэв TextInitial дотор байгаа нэр өгөгдлийн санд байхгүй бол:
     *      - нэрсийн жагсаалтад нэмнэ
     *      - мөн TextModel→setTable() дуудаж хүснэгтийн structure бэлэн эсэхийг хангана.
     *
     * Давуу тал:
     * ----------
     * • Орчуулгын модуль нэмж хөгжүүлэхэд schema migration шаарддаггүй.
     * • Seed function бий болгосноор UI-д автоматаар харагдана.
     *
     * @return array
     *      Бүх орчуулгын хүснэгтийн нэрс.
     */
    private function getTextTableNames(): array
    {
        if ($this->getDriverName() == 'pgsql') {
            $query = 
                'SELECT tablename FROM pg_catalog.pg_tables ' .
                "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like 'localization_text_%_content'";
        } else {
            $query = 'SHOW TABLES LIKE ' . $this->quote('localization_text_%_content');
        }
        $names = [];
        $content_tables = $this->query($query)->fetchAll();
        foreach ($content_tables as $result) {
           $names[] = \substr(reset($result), \strlen('localization_text_'), -\strlen('_content'));
        }

        $initials = \get_class_methods(TextInitial::class);
        foreach ($initials as $value) {
            $initial = \substr($value, \strlen('localization_text_'));
            if (!empty($initial) && !\in_array($initial, $names)) {
                $names[] = $initial;
                (new TextModel($this->pdo))->setTable($initial);
            }
        }
        
        return $names;
    }
    
    /**
     * Системийн бүх орчуулгын хүснэгт дундаас keyword давхцаж буй эсэхийг шалгана.
     *
     * @param array $from
     *      getTextTableNames() → ['default', 'dashboard', 'user', ...]
     *
     * @param string $keyword
     *      Шалгах түлхүүр үг.
     *
     * Ажиллах зарчим:
     * ----------------
     * 1. Хүснэгт бүрийг давтан:
     *      SELECT * FROM localization_text_{table} WHERE keyword=:1 LIMIT 1
     * 2. Хэрэв бичлэг олдвол:
     *      return ['table' => {table}, 'id' => {id}, ...];
     * 3. Олдохгүй бол false буцаана.
     *
     * Ашиглах зорилго:
     * ----------------
     * • Нэг keyword-г өөр өөр модулиудад давхар ашиглахгүй байх
     * • INSERT/UPDATE үед системийн түвшний нэр давхцахгүй байхыг баталгаажуулах
     *
     * @return array|false
     *      Давхцсан бичлэг олдвол мэдээлэлтэй массив, олдохгүй бол false.
     */
    private function findByKeyword(array $from, string $keyword): array|false
    {
        foreach ($from as $name) {
            $select = $this->prepare("SELECT * FROM localization_text_$name WHERE keyword=:1 LIMIT 1");
            $select->bindParam(':1', $keyword);
            if (!$select->execute()) {
                continue;
            }
            if ($select->rowCount() == 1) {
                return ['table' => $name] + $select->fetch();
            }
        }
        
        return false;
    }
}
