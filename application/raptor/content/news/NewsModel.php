<?php

namespace Raptor\Content;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class NewsModel
 *
 * Мэдээний (`news`) хүснэгттэй ажиллах өгөгдлийн загвар (Model) класс.
 * Энэ класс нь Indoraptor Framework-ийн DataObject\Model-ийг ашиглан
 * мэдээний хүснэгтийн бүтцийг тодорхойлох, CRUD болон өгөгдлийн агуулахтай
 * уялдаа холбоо үүсгэх үүрэгтэй.
 *
 * Үндсэн боломжууд:
 *  - Мэдээний хүснэгтийн багануудыг тодорхойлох
 *    (id, title, description, content, photo, code, type, category, гэх мэт)
 *  - FK constraint-уудыг анхны тохиргоонд үүсгэх
 *  - Шинэ мэдээ үүсгэх үед created_at талбарыг автоматаар бөглөх
 *  - Мэдээний төрөл (type), ангилал (category) зэрэг талбаруудыг удирдах
 *  - Хэлний код (code) ашиглан олон хэл дээрх мэдээний удирдлага
 *
 * Хүснэгтийн талбарууд:
 *  - id (bigint, primary) - Мэдээний өвөрмөц дугаар
 *  - title (varchar 255) - Мэдээний гарчиг
 *  - description (text) - Мэдээний товч тайлбар
 *  - content (mediumtext) - Мэдээний бүтэн агуулга
 *  - photo (varchar 255) - Мэдээний зураг (файлын зам)
 *  - code (varchar 2) - Хэлний код (mn, en, гэх мэт)
 *  - type (varchar 32, default: 'common') - Мэдээний төрөл
 *  - category (varchar 32, default: 'general') - Мэдээний ангилал
 *  - comment (tinyint, default: 1) - Сэтгэгдэл идэвхтэй эсэх
 *  - read_count (bigint, default: 0) - Уншсан тоо
 *  - is_active (tinyint, default: 1) - Идэвхтэй эсэх
 *  - published (tinyint, default: 0) - Нийтлэгдсэн эсэх
 *  - published_at (datetime) - Нийтлэгдсэн огноо
 *  - published_by (bigint) - Нийтлэсэн хэрэглэгчийн ID
 *  - created_at (datetime) - Үүсгэсэн огноо
 *  - created_by (bigint) - Үүсгэсэн хэрэглэгчийн ID
 *  - updated_at (datetime) - Шинэчлэсэн огноо
 *  - updated_by (bigint) - Шинэчлэсэн хэрэглэгчийн ID
 *
 * Хүснэгт ашиглалт:
 *  - Мэдээ → Хэрэглэгч (published_by, created_by, updated_by)
 *
 * @package Raptor\Content
 */
class NewsModel extends Model
{
    /**
     * NewsModel constructor.
     *
     * PDO instance-г оноож, мэдээний хүснэгтийн бүх багануудыг тодорхойлно.
     * Хүснэгтийн нэрийг 'news' гэж тохируулна.
     *
     * ⚡ **PDO Injection тухай тэмдэглэл**
     * --------------------------------------------------------------
     * Indoraptor Framework нь PDO instance-ийг дараах дарааллаар inject хийдэг:
     *
     *   1) MySQLConnectMiddleware / PostgresConnectMiddleware
     *      → Application-ийн ServerRequestInterface дотор PDO instance үүсгэнэ
     *      → $request->withAttribute('pdo', $pdo) хэлбэрээр хадгална
     *
     *   2) Controller constructor
     *      → ServerRequest-аас PDO-г авч $this->pdo хувьсагчид онооно
     *      → $this->pdo = $request->getAttribute('pdo')
     *
     *   3) Model constructor (энэ функц)
     *      → Controller-ийн байгуулагчаар дамжуулан PDO instance ирнэ
     *      → new NewsModel($this->pdo) хэлбэрээр дуудагдана
     *
     * Иймээс энэхүү `$pdo` нь *middleware injection-ээр дамжсан баталгаатай
     * холболт* бөгөөд Model анги зөвхөн өгөгдөлтэй ажиллахад анхаарна.
     *
     * ✔ Framework-ийн request-scope injection механизм ашигладаг
     * ✔ Нэг request дотор нэг л PDO instance ажиллана
     * ✔ DatabaseMiddleware нь PDO-г автоматаар үүсгэж inject хийнэ
     *
     * @param \PDO $pdo Database connection instance.
     *                  Application-ийн ServerRequestInterface дотор inject хийгдсэн
     *                  PDO object (DatabaseMiddleware inject хийсэн) байгаа нь
     *                  Controller-ийн байгуулагчаар дамжуулан ирдэг.
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('title', 'varchar', 255),
            new Column('description', 'text'),
            new Column('content', 'mediumtext'),
            new Column('photo', 'varchar', 255),
            new Column('code', 'varchar', 2),
           (new Column('type', 'varchar', 32))->default('common'),
           (new Column('category', 'varchar', 32))->default('general'),
           (new Column('comment', 'tinyint'))->default(1),
           (new Column('read_count', 'bigint'))->default(0),
           (new Column('is_active', 'tinyint'))->default(1),
           (new Column('published', 'tinyint'))->default(0),
            new Column('published_at', 'datetime'),
            new Column('published_by', 'bigint'),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);
        
        $this->setTable('news');
    }
    
    /**
     * Анхны тохиргоо (initial setup).
     *
     * Хүснэгт анх үүсэх үед foreign key constraint-уудыг автоматаар үүсгэнэ.
     * Энэ функц нь:
     *  - published_by → users(id) foreign key
     *  - created_by → users(id) foreign key
     *  - updated_by → users(id) foreign key
     *
     * Бүх foreign key-ууд ON DELETE SET NULL, ON UPDATE CASCADE бүтэцтэй.
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();

        // SQLite нь ALTER TABLE ... ADD CONSTRAINT дэмжихгүй
        // MySQL/PostgreSQL дээр л FK constraint нэмнэ
        if ($this->getDriverName() != 'sqlite') {
            $this->setForeignKeyChecks(false);
            
            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
            
            // Foreign key constraint-ууд үүсгэх
            $constraints = [
                'published_by' => "{$table}_fk_published_by",
                'created_by'   => "{$table}_fk_created_by",
                'updated_by'   => "{$table}_fk_updated_by"
            ];
            
            foreach ($constraints as $column => $constraint) {
                $this->exec(
                    "ALTER TABLE $table " .
                    "ADD CONSTRAINT $constraint " .
                    "FOREIGN KEY ($column) " .
                    "REFERENCES $users(id) " .
                    "ON DELETE SET NULL " .
                    "ON UPDATE CASCADE"
                );
            }
            
            $this->setForeignKeyChecks(true);
        }
    }
    
    /**
     * Шинэ мэдээ үүсгэх.
     *
     * Мэдээний бичлэг үүсгэх үед created_at талбарыг автоматаар
     * одоогийн огноо цагаар бөглөнө (хэрэв өгөгдөөгүй бол).
     *
     * @param array $record Мэдээний мэдээлэл (title, description, content, гэх мэт)
     * @return array|false Амжилттай бол үүссэн бичлэгийн массив, бусад тохиолдолд false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }
}
