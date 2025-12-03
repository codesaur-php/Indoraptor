<?php

namespace Raptor\Content;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class FilesModel
 *
 * Хүснэгт бүр дээр хавсаргасан файлуудыг хадгалах зориулалттай
 * “*_{table}_files*” дагалдах хүснэгтийн модел.
 *
 * --------------------------------------------------------------
 * 📌 Үндсэн хэрэглээ
 * --------------------------------------------------------------
 *  Жишээ нь:
 *      users → users_files
 *      pages → pages_files
 *
 *  Нэг үндсэн бичлэг (record_id) олон файлтай холбогдох боломжтой.
 *
 * --------------------------------------------------------------
 * 🧩 Онцлог боломжууд
 * --------------------------------------------------------------
 *  • Таблын нэр автоматаар `{table}_files` болгон хувиргана  
 *  • created_by / updated_by → users.id талбарууд дээр FK автоматаар үүсгэнэ  
 *  • record_id → тухайн гол хүснэгтийн FK (cascade delete)  
 *  • insert/update үед created_at / updated_at автоматаар бөглөгдөнө  
 *  • FileController зэрэг upload controller-тэй шууд нийцдэг  
 *
 * --------------------------------------------------------------
 * 🔗 Middleware ба PDO injection
 * --------------------------------------------------------------
 *  Raptor\Controller нь PDOTrait ашигладаг тул
 *  PDO-г middleware нь `$request->getAttribute('pdo')` хэлбэрээр inject хийдэг.
 *  Иймээс FilesModel дотор `$this->setInstance($pdo)` гэж авна.
 *
 * @package Raptor\Content
 */
class FilesModel extends Model
{
    /**
     * FilesModel constructor.
     *
     * @param \PDO $pdo
     *      Middleware → ServerRequest → attribute('pdo') хэлбэрээр
     *      автоматаар ирсэн PDO instance.
     *
     * Багана (column)–уудыг бүртгэнэ:
     *   - record_id  : гол хүснэгтийн id FK
     *   - file       : сервер дээрх локал абсолют path
     *   - path       : public URL (client-д үзэгдэх)
     *   - size       : файл байтын хэмжээ
     *   - type       : image / audio / video / application …
     *   - mime_content_type : MIME type
     *   - category / keyword / description : тайлбар
     *   - created/updated талбарууд
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('record_id', 'bigint'),
            new Column('file', 'varchar', 255),
           (new Column('path', 'varchar', 255))->default(''),
            new Column('size', 'int'),
            new Column('type', 'varchar', 24),
            new Column('mime_content_type', 'varchar', 127),
            new Column('category', 'varchar', 24),
            new Column('keyword', 'varchar', 32),
            new Column('description', 'varchar', 255),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);
    }
    
    /**
     * Үндсэн хүснэгтийн нэрнээс "{table}_files" нэр гарган тохируулна.
     *
     * @param string $name  Гол хүснэгтийн нэр (жишээ: users, pages)
     *
     * @throws Exception Хэрэв хүснэгтийн нэр хоосон эсвэл буруу бол.
     *
     * setTable("users") → "users_files"
     */
    public function setTable(string $name)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \Exception(__CLASS__ . ': Table name must be provided', 1103);
        }
        
        parent::setTable("{$table}_files");
    }

    /**
     * FilesModel-ийн үндсэн parent хүснэгтийн нэрийг буцаана.
     *
     * Жишээ:
     *   files table → users_files  → parent = "users"
     *
     * @return string
     */
    public function getRecordName(): string
    {
        return \substr($this->getName(), 0, -(\strlen('_files')));
    }
    
     /**
     * FilesModel үүсэх үед шаардлагатай FK constraint-уудыг автоматаар үүсгэнэ.
     *
     * 1) created_by → users(id)
     * 2) updated_by → users(id)
     * 3) record_id  → parent_table(id)
     *
     * Хэрэв parent хүснэгт байхгүй бол 3-р FK үүсгэхгүй.
     *
     * ON DELETE CASCADE → гол бичлэг уствал бүх файлууд автоматаар устна.
     *
     * @return void
     */
    protected function __initial()
    {
        $this->setForeignKeyChecks(false);
        $my_name = $this->getName();
        $record_name = $this->getRecordName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        if ($this->hasTable($record_name)) {
            $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_record_id FOREIGN KEY (record_id) REFERENCES $record_name(id) ON DELETE CASCADE ON UPDATE CASCADE");            
        }
        $this->setForeignKeyChecks(true);
    }
    
    /**
     * insert()
     * ---------------------------------------------------------
     *  Бичлэг шинээр үүсгэх үед created_at утгыг автоматаар populate
     *  хийдэг override функц (хэрвээ шинэ утгууд дотор агуулагдаагүй бол).
     *
     * @param array $record
     * @return array|false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }
    
    /**
     * updateById()
     * ---------------------------------------------------------
     * @param int $id         Засах бичлэгийн ID
     * @param array $record   Шинэ утгууд
     *
     * @return array|false
     *
     *  Бичлэг шинэчилж буй үед updated_at-г автоматаар онооно
     *  (хэрвээ шинэ утгууд дотор агуулагдаагүй бол).
     */
    public function updateById(int $id, array $record): array|false
    {
        $record['updated_at'] ??= \date('Y-m-d H:i:s');
        return parent::updateById($id, $record);
    }
}
