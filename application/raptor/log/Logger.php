<?php

namespace Raptor\Log;

use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;

use codesaur\DataObject\Column;

/**
 * Logger - PSR-3 стандарт дээр суурилсан Indoraptor-ийн лог бичигч.
 *
 * Онцлог:
 * ───────────────────────────────────────────────────────────────────
 * • PSR-3 лог интерфейс (AbstractLogger) бүрэн нийцтэй
 * • Лог бүрийг мэдээллийн сан дахь “*_log” хүснэгтэд хадгална
 * • Table нэр нь динамик: setTable('dashboard') → dashboard_log
 * • Password, JWT, Token зэрэг нууц мэдээллийг автоматаар маскална
 * • Context → JSON хэлбэрээр хадгална
 * • {context.key} хэлбэрийн placeholder interpolation дэмждэг
 *
 * Лог бүтэц:
 * ───────────────────────────────────────────────────────────────────
 * id          BIGINT (PK)
 * level       VARCHAR(16)
 * message     TEXT  - интерполяцлогдсон мессеж
 * context     MEDIUMTEXT (JSON)
 * created_at  TIMESTAMP
 *
 * Ашиглах зарчим:
 * ───────────────────────────────────────────────────────────────────
 *   $logger = new Logger($pdo);
 *   $logger->setTable('dashboard');
 *   $logger->log(LogLevel::INFO, 'User {auth.id} logged in', [
 *       'auth' => ['id' => 5]
 *   ]);
 */
class Logger extends AbstractLogger
{
    use \codesaur\DataObject\TableTrait;

    /**
     * Лог бичих хүснэгтийн багануудын анхны конфигураци.
     *
     * Column-ууд нь өөрчлөгдөхгүй, setColumns() override хийхийг хориглоно.
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->columns = [
            'id'         => (new Column('id', 'bigint'))->primary(),
            'level'      => (new Column('level', 'varchar', 16))->default(LogLevel::NOTICE),
            'message'    => (new Column('message', 'text'))->notNull(),
            'context'    => (new Column('context', 'mediumtext'))->notNull(),
            'created_at' =>  new Column('created_at', 'timestamp')
        ];
    }

    /**
     * Лог хадгалах хүснэгтийн нэрийг тохируулна.
     *
     * @param string $name  (жишээ: "dashboard")
     *
     * Жич:
     *   - Хүснэгт байхгүй бол автоматаар үүсгэнэ.
     *   - setTable('dashboard') → "dashboard_log" нэртэй хүснэгт үүснэ.
     */
    public function setTable(string $name)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \InvalidArgumentException(__CLASS__ . ': Logger table name must be provided', 1103);
        }

        $this->name = "{$table}_log";

        if ($this->hasTable($this->name)) {
            return;
        }

        $this->createTable($this->name, $this->getColumns());
        $this->__initial();
    }

    /**
     * 
     * Лог хүснэгтийн анхны тохиргоо.
     *
     * Энэ хүснэгт нь зөвхөн "append-only" төрлийн event log тул:
     *   • FK (гадаад түлхүүр) шаардлагагүй  
     *   • Seed / анхны өгөгдөл хэрэггүй  
     *   • Ямар нэгэн нэмэлт индекс, хамаарал үүсгэх шаардлагагүй  
     *
     * Тиймээс Logger хүснэгт шинээр үүсэх үед __initial() хоосон үлдэнэ.
     *
     * Үндсэн зорилго:
     *   - Лог нь системийн бусад хүснэгтээс хараат бус байх
     *   - Алдааны үед лог бичих боломжийг 100% хадгалах
     *   - FK lock / delete cascade зэрэг эрсдэлээс хамгаалах
     *
     * Жич:
     *   Хэрвээ Logger-д FK тавибал - хэрэглэгч, байгууллага эсвэл
     *   контент устах үед бүх лог устах эрсдэлтэй тул хатуу хориотой.
     *
     * @return void
     */
    protected function __initial()
    {
    }
    
    /**
     * Column-уудыг өөрчлөхийг хориглоно.
     *
     * PSR-3 стандартын дагуу лог бичигчийн хүснэгтийн бүтэц
     * (id, level, message, context, created_at) нь
     * Logger-ийн constructor дээр аль хэдийн тогтмол
     * (predefined) байдлаар тодорхойлогдсон байдаг.
     *
     * Лог бүр нь audit trail үүрэгтэй тул:
     *   • баганы нэрийг өөрчлөх
     *   • шинээр багана нэмэх
     *   • багана устгах
     *
     * зэрэг динамик өөрчлөлт хийхийг хатуу хориглоно.
     *
     * Учир нь ийм өөрчлөлт нь:
     *   - Логийн бүрэн бүтэн байдал (audit integrity)-г алдагдуулна
     *   - PSR-3-ын нэгэн төрлийн (consistent) log format-ийг эвдэнэ
     *   - Framework-ийн бусад хэсгийн log-холбоотой кодыг эвдэнэ
     *
     * Иймээс Logger хүснэгтийн бүтэц immutable байх ёстой.
     *
     * @throws \RuntimeException  Хэрвээ column өөрчлөх оролдлого хийвэл
     */
    public function setColumns(array $columns)
    {
        throw new \RuntimeException(__CLASS__ . ": You can't change predefined columns of Logger table!");
    }

    /**
     * PSR-3 log() - үндсэн лог бичигч.
     *
     * @param string $level         LogLevel::INFO, ERROR, ALERT гэх мэт
     * @param string|\Stringable $message  Интерполяцлагдаж болох message
     * @param array  $context       Лог контекст (давхар маскална)
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (empty($this->name)) {
            return;
        }

        $record = [
            'level'      => (string)$level,
            'message'    => $message,
            'created_at' => \date('Y-m-d H:i:s'),
            'context'    => $this->encodeContext($context)
        ];
        $column = $param = [];
        foreach (\array_keys($record) as $key) {
            $column[] = $key;
            $param[]  = ":$key";
        }
        $columns = \implode(', ', $column);
        $params  = \implode(', ', $param);
        $insert = $this->prepare("INSERT INTO $this->name($columns) VALUES($params)");
        foreach ($record as $name => $value) {
            $insert->bindValue(":$name", $value, $this->getColumn($name)->getDataType());
        }
        $insert->execute();
    }

    /**
     * Message interpolation - {key.subkey} хэлбэрийн placeholder-уудыг
     * context утгаар солино.
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    private function interpolate(string $message, array $context = []): string
    {
        $flat = $this->flattenArray($context);
        $replace = [];
        foreach ($flat as $key => $val) {
            $replace['{' . $key . '}'] = (string)$val;
        }
        return \strtr($message, $replace);
    }

    /**
     * Хадгалсан бүх лог жагсаалтыг буцаана.
     *
     * @param array $condition SQL WHERE/ORDER нөхцөл
     * @return array
     */
    public function getLogs(array $condition = []): array
    {
        $rows = [];
        try {
            if (empty($condition)) {
                $condition['ORDER BY'] = 'id Desc';
            }
            $stmt = $this->selectStatement($this->getName(), '*', $condition);

            while ($record = $stmt->fetch()) {
                $rows[] = $this->normalizeLogRecord($record);
            }
        } catch (\Throwable $err) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
        }
        return $rows;
    }

    /**
     * Нэг log бичлэгийг ID-р унших.
     *
     * @return array|null
     */
    public function getLogById(int $id): array|null
    {
        $stmt = $this->prepare("SELECT * from $this->name WHERE id=$id LIMIT 1");
        if (!$stmt->execute() || $stmt->rowCount() != 1) {
            return null;
        }

        return $this->normalizeLogRecord($stmt->fetch());
    }

    /**
     * Context-ийг JSON болгож serialize хийнэ.
     *
     * Аюулгүй байдлын шалтгаан:
     * ────────────────────────────────────────────────────────────────
     *  Лог файл болон мэдээллийн сан нь хэзээ нэг өдөр:
     *     • алдагдах
     *     • нэвтрэх эрхгүй хэрэглэгч харах
     *     • backup-оор дамжин гуравдагч этгээдэд очих
     *  зэрэг эрсдэлтэй байдаг.
     *
     *  Тиймээс PASSWORD, TOKEN, JWT зэрэг эмзэг түлхүүрүүдийг
     *  лог дээр цэвэр текстээр хадгалбал маш ноцтой
     *  аюулгүй байдлын зөрчил болно.
     *
     *  → Ийм төрлийн түлхүүр илэрвэл "*** hidden ***" гэж автоматаар маскална.
     */
    private function encodeContext(array $context): string
    {
        \array_walk_recursive($context, function (&$value, $k) {
            $key     = \strtoupper($k);
            $secrets = ['PIN', 'JWT', 'TOKEN'];

            // Эмзэг түлхүүр таарвал лог дээр plaintext хадгалахыг хориглоно
            if (\str_contains($key, 'PASSWORD') || \in_array($key, $secrets)) {
                $value = '*** hidden ***';
            }
        });

        $json = \json_encode($context, \JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $context = \mb_convert_encoding($context, 'UTF-8', 'UTF-8');
            $json = \json_encode($context, \JSON_INVALID_UTF8_SUBSTITUTE);
        }
        return $json ?: \json_encode(['log-context-write-error' => \json_last_error_msg()]);
    }

    /**
     * Лог бичлэгийн context-г decode хийж, message interpolation хийж,
     * ашиглахад бэлэн болгож форматлана.
     */
    private function normalizeLogRecord(array $record): array
    {
        $record['context'] =
            \json_decode($record['context'], true, 100000, \JSON_INVALID_UTF8_SUBSTITUTE)
            ?? ['log-context-read-error' => \json_last_error_msg()];
        $record['message'] = $this->interpolate($record['message'], $record['context'] ?? []);
        return $record;
    }

    /**
     * Оролтын context array-г flat хэлбэрт хөрвүүлнэ.
     *
     *  Жишээ:
     *      ['a' => ['b' => 1]] → ['a.b' => 1]
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (\is_array($value)) {
                $result += $this->flattenArray($value, $newKey);
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }
}
