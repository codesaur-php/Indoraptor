<?php

namespace Raptor\Content;

use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

/**
 * Class ReferenceModel
 *
 * Reference (танилцуулга, мэдээллийн сан, тайлбарын төрөлтэй текстүүд) зэрэг
 * олон хэл дээр хадгалагдах бүхий л reference контентыг удирдах зориулалттай
 * локалчилсон өгөгдлийн загвар (LocalizedModel) юм.
 *
 * Онцлог:
 * -------
 * • Хүснэгтийн нэр нь динамик - setTable("questions") → reference_questions
 * • Контент нь олон хэл дээр хадгалагдана (title, content)
 * • Гол талбарууд: keyword, category, created_by, updated_by гэх мэт
 * • __initial() функц нь анхны бүтэц болон гадаад түлхүүрийг автоматаар үүсгэнэ
 * • Хэрэв ReferenceInitial::$table() seed арга байвал анхны өгөгдөл нэмж өгнө
 *
 * Энэ модель нь:
 *   - FAQ
 *   - About us
 *   - Help center
 *   - Terms / Privacy content
 * гэх мэт олон хэл дээр орчуулагдах, динамик бүтэцтэй reference мэдээлэлд ашиглагдана.
 */
class ReferenceModel extends LocalizedModel
{
    /**
     * ReferenceModel constructor.
     *
     * PDO instance-г оноож, үндсэн болон контент талбаруудыг тодорхойлно.
     * LocalizedModel нь үндсэн хүснэгт + *_content хүснэгт гэсэн хоёр давхар
     * бүтэцтэй тул setContentColumns() нь тухайн хэл тус бүрийн контентыг тодорхойлдог.
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        /* Үндсэн хүснэгтийн баганууд */
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('keyword', 'varchar', 128))->unique(),
            new Column('category', 'varchar', 32),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);

        /* Олон хэлний контент хэсгийн баганууд */
        $this->setContentColumns([
            new Column('title', 'varchar', 255),
            new Column('content', 'mediumtext')
        ]);
    }

    /**
     * Ашиглах хүснэгтийн нэрийг тохируулна.
     *
     * Жишээ:
     *    setTable("questions") → reference_questions
     *
     * Хүснэгтийн нэр зөвхөн латин үсэг, тоо, _ болон - тэмдэгтүүдийг зөвшөөрнө.
     *
     * @param string $name
     * @throws \InvalidArgumentException
     */
    public function setTable(string $name)
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);

        if (empty($table)) {
            throw new \InvalidArgumentException(
                __CLASS__ . ': Table name must be provided',
                1103
            );
        }

        /* LocalizedModel дахь dynamic table + content table-ийг үүсгэнэ */
        parent::setTable("reference_$table");
    }

    /**
     * Моделийн анхны тохиргоо.
     *
     * Хийгдэх ажлууд:
     * ----------------
     * 1) created_by / updated_by талбаруудад UsersModel руу FK холболт үүсгэнэ
     * 2) reference_{table} + reference_{table}_content хүснэгт байхгүй бол автоматаар үүсгэнэ
     * 3) Хэрэв ReferenceInitial класс дахь тухайн хүснэгтэд зориулсан
     *    seed функц (reference_templates гэх мэт) байвал анхны өгөгдөл импортлоно
     *
     * __initial() нь LocalizedModel::setTable() дотор автоматаар дуудагдана.
     */
    protected function __initial()
    {
        $table = $this->getName();

        // SQLite нь ALTER TABLE ... ADD CONSTRAINT дэмжихгүй
        // MySQL/PostgreSQL дээр л FK constraint нэмнэ
        if ($this->getDriverName() != 'sqlite') {
            $this->setForeignKeyChecks(false);

            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();

            /* FK - created_by / updated_by талбаруудыг хэрэглэгчийн хүснэгттэй холбох */
            $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by 
                FOREIGN KEY (created_by) REFERENCES $users(id)
                ON DELETE SET NULL ON UPDATE CASCADE");

            $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by 
                FOREIGN KEY (updated_by) REFERENCES $users(id)
                ON DELETE SET NULL ON UPDATE CASCADE");

            $this->setForeignKeyChecks(true);
        }

        /* Seed function байвал түүнийг ажиллуулна */
        if (\method_exists(ReferenceInitial::class, $table)) {
            ReferenceInitial::$table($this);
        }
    }

    /**
     * Reference бичлэг шинээр үүсгэх.
     *
     * @param array $record   - үндсэн хүснэгтийн өгөгдөл
     * @param array $content  - олон хэлний контент
     * @return array|false
     * 
     * created_at талбарыг ирүүлээгүй бол автомат онооно.
     */
    public function insert(array $record, array $content): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record, $content);
    }

    /**
     * id дугаар дээрх reference бичлэгийг шинэчлэх.
     *
     * @param int $id
     * @param array $record
     * @param array $content
     * @return array|false
     * 
     * updated_at талбарыг ирүүлээгүй бол автомат онооно.
     */
    public function updateById(int $id, array $record, array $content): array|false
    {
        $record['updated_at'] ??= \date('Y-m-d H:i:s');
        return parent::updateById($id, $record, $content);
    }
}
