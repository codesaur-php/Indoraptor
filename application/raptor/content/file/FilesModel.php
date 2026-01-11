<?php

namespace Raptor\Content;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class FilesModel
 *
 * --------------------------------------------------------------
 * 📌 FilesModel гэж юу вэ?
 * --------------------------------------------------------------
 *  Энэ модел нь Indoraptor Framework дахь бүх контент хүснэгт дээр
 *  хавсаргасан файлуудыг хадгалах зориулалттай “дагалдах хүснэгт” юм.
 *
 *  Хүснэгтийн нэр нь үргэлж:
 *      {table}_files
 *
 *  Жишээ:
 *      pages  → pages_files
 *      news   → news_files
 *      files  → files_files
 *
 * --------------------------------------------------------------
 * 📌 record_id талбарын утга
 * --------------------------------------------------------------
 *  record_id нь тухайн файлыг аль үндсэн контент бичлэгтэй холбож
 *  байгааг заадаг FK (foreign key) талбар.
 *
 *  ✔ Нэг контент олон файлтай холбогдож болно.
 *  ✔ record_id = гол хүснэгтийн id.
 *
 *  🔍 Жишээ:
 *      pages хүснэгт:
 *          id = 10  → "About Us" page
 *
 *      pages_files хүснэгт:
 *          record_id = 10 бүхий олон мөр (олон файл) байж болно.
 *
 *      Энэ нь тухайн page-ийн бүх хавсаргагдсан файлуудыг дараах SQL-аар авах боломжтой:
 *
 *          SELECT * FROM pages_files WHERE record_id = 10 AND is_active=1;
 *
 *  ➤ Үүнийг Content module бүхэлдээ ашигладаг:
 *      - FilesController::post()
 *      - FilesController::list()
 *      - PagesController / NewsController  гэх мэтчилэн
 *
 * --------------------------------------------------------------
 * 📌 `$id = 0` тохиолдол
 * --------------------------------------------------------------
 *  Хэрэв record_id = 0 бол файл нь ямар ч контент мөртэй
 *  холбогдоогүй "ерөнхий upload" гэсэн үг.
 *
 *      /files/logo.png
 *
 *  Энэ нь:
 *    • ерөнхий файл  
 *    • түр хадгалсан файл  
 *    • контент сонгоогүй upload  
 *  зэрэг нөхцөлд ашиглагдана.
 *
 * --------------------------------------------------------------
 * 🧩 FilesModel-ийн онцлог
 * --------------------------------------------------------------
 *  • Хүснэгтийн нэрийг setTable("pages") → "pages_files" болгон автоматаар хувиргана  
 *  • created_by / updated_by баганууд users(id) руу FK холбоос үүсгэнэ  
 *  • record_id → parent_table(id) FK (cascade update / set null delete)  
 *  • insert/update үед created_at / updated_at талбарууд автоматаар бөглөгдөнө  
 *  • FileController-тай шууд нийцэн ажилладаг:
 *        - moveUploaded()
 *        - formatSizeUnits()
 *
 * --------------------------------------------------------------
 * 🔗 Middleware ба PDO injection
 * --------------------------------------------------------------
 *  Raptor\Controller нь PDOTrait ашигладаг тул PDO instance нь:
 *      $request->getAttribute('pdo')
 *  хэлбэрээр ирдэг.
 *
 *  FilesModel constructor нь энэ PDO-г:
 *      $this->setInstance($pdo)
 *  гэж авна.
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
     *      автоматаар ирдэг PDO instance.
     *
     * ----------------------------------------------------------
     * 📌 Багануудын бүтэц
     * ----------------------------------------------------------
     *  id                 - Мөрийн ID (primary key)
     *  record_id          - Үндсэн хүснэгтийн бичлэгийн id дугаар (FK)
     *  file               - Сервер доторх локал физик файл (absolute path)
     *  path               - Хэрэглэгч харах public file URL
     *  size               - Файлын хэмжээ (byte)
     *  type               - Файлын төрөл (image, audio, video, application…)
     *  mime_content_type  - MIME type (image/png гэх мэт)
     *  category           - Файлын ангилал (optional)
     *  keyword            - Түлхүүр үг (optional)
     *  description        - Тайлбар (optional)
     *  is_active          - 1 → идэвхтэй / 0 → soft delete хийгдсэн
     *  created_at         - Үүссэн огноо
     *  created_by         - Үүсгэсэн хэрэглэгч (users.id)
     *  updated_at         - Зассан огноо
     *  updated_by         - Зассан хэрэглэгч (users.id)
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
     * @param string $name  Гол хүснэгтийн нэр (жишээ: news, pages)
     *
     * @throws Exception Хэрэв хүснэгтийн нэр хоосон эсвэл буруу бол.
     *
     * setTable("news") → "news_files"
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
     *   files table → news_files  → parent = "news"
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
     * @return void
     */
    protected function __initial()
    {
        $my_name = $this->getName();

        // SQLite нь ALTER TABLE ... ADD CONSTRAINT дэмжихгүй
        // MySQL/PostgreSQL дээр л FK constraint нэмнэ
        if ($this->getDriverName() != 'sqlite') {
            $this->setForeignKeyChecks(false);
            $record_name = $this->getRecordName();
            // users хүснэгтийн нэрийг UsersModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
            $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_created_by FOREIGN KEY (created_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
            $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_updated_by FOREIGN KEY (updated_by) REFERENCES $users(id) ON DELETE SET NULL ON UPDATE CASCADE");
            if ($this->hasTable($record_name)) {
                $this->exec("ALTER TABLE $my_name ADD CONSTRAINT {$my_name}_fk_record_id FOREIGN KEY (record_id) REFERENCES $record_name(id) ON DELETE SET NULL ON UPDATE CASCADE");            
            }
            $this->setForeignKeyChecks(true);
        }
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
