<?php

namespace Raptor\Localization;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class LanguageModel
 *
 * Нутагшуулалтын модульд ашиглагддаг "localization_language" хүснэгтийн
 * мэдээллийн загвар (Model) юм.
 *
 * Энэхүү модель нь:
 *  - Системд ашиглагдах боломжит хэлүүдийг бүртгэх
 *  - Хэл тутмын үндсэн мэдээлэл (код, locale, нэр, тайлбар) хадгалах
 *  - Хэл идэвхтэй/идэвхгүй эсэхийг удирдах
 *  - Default хэл тодорхойлох
 *  - CRUD болон хайлт (retrieve, getByCode) үйлдлүүдийг гүйцэтгэх
 *
 * Мөн анхны суулгалтын үед (initial):
 *  - created_by / updated_by талбаруудад FK холбоос үүсгэнэ
 *  - Монгол (mn-MN) болон Англи (en-US) 2 хэл автоматаар бүртгэнэ
 *
 * @package Raptor\Localization
 */
class LanguageModel extends Model
{
    /**
     * LanguageModel constructor.
     *
     * @param \PDO $pdo  PDO instance - мэдээллийн сантай ажиллах холболт
     *
     * Хүснэгтийн багана (Column)-уудыг тодорхойлно:
     *  - id: анхдагч түлхүүр
     *  - code: 2 оронтой хэлний код (mn, en гэх мэт)
     *  - locale: системийн locale код (mn-MN, en-US)
     *  - title: хэлний нэр
     *  - description: тайлбар
     *  - is_default: уг хэл default эсэх
     *  - is_active: идэвхтэй эсэх
     *  - created_at / created_by: бүтээгдсэн огноо, хэрэглэгч
     *  - updated_at / updated_by: шинэчлэгдсэн огноо, хэрэглэгч
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('code', 'varchar', 2))->unique(),
           (new Column('locale', 'varchar', 11))->unique(),
           (new Column('title', 'varchar', 128))->unique(),
            new Column('description', 'varchar', 255),
           (new Column('is_default', 'tinyint'))->default(0),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);

        $this->setTable('localization_language');
    }

    /**
     * Хэлүүдийн жагсаалт авах.
     *
     * @param int $is_active  Идэвхтэй хэл эсэх (default: 1)
     * @return array          Хэлүүдийг [code => ['locale'=>..., 'title'=>...]] хэлбэрээр буцаана
     *
     * Хүснэгтээс идэвхтэй хэлүүдийг кодын дагуу татан авч,
     * locale болон title мэдээллийг агуулсан массив болгон буцаана.
     */
    public function retrieve(int $is_active = 1)
    {
        $languages = [];
        $condition = [
            'WHERE' => "is_active=$is_active",
            'ORDER BY' => 'is_default Desc'
        ];
        $stmt = $this->selectStatement($this->getName(), '*', $condition);
        while ($row = $stmt->fetch()) {
            $languages[$row['code']] = [
                'locale' => $row['locale'],
                'title'  => $row['title']
            ];
        }
        return $languages;
    }

    /**
     * Кодын дагуу нэг хэлний мэдээлэл авах.
     *
     * @param string $code        Хэлний код (mn, en гэх мэт)
     * @param int    $is_active   Хэл идэвхтэй эсэх
     * @return array|false        Олдсон мөр эсвэл false буцаана
     */
    public function getByCode(string $code, int $is_active = 1)
    {
        return $this->getRowWhere([
            'code' => $code,
            'is_active' => $is_active
        ]);
    }

    /**
     * Хүснэгтийг анх үүсгэх үед хийгдэх тохиргоо.
     *
     * - Foreign Key холбоосууд нэмэгдэнэ (created_by, updated_by)
     * - Монгол болон Англи хэл анхны мэдээлэл болгон бүртгэгдэнэ
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

            /* created_by → users(id) холбоос */
            $this->exec(
                "ALTER TABLE $table 
                 ADD CONSTRAINT {$table}_fk_created_by 
                 FOREIGN KEY (created_by) REFERENCES $users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE"
            );

            /* updated_by → users(id) холбоос */
            $this->exec(
                "ALTER TABLE $table 
                 ADD CONSTRAINT {$table}_fk_updated_by 
                 FOREIGN KEY (updated_by) REFERENCES $users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE"
            );

            $this->setForeignKeyChecks(true);
        }

        /* Анхны 2 хэл бүртгэх */
        $nowdate = \date('Y-m-d H:i:s');
        $query =
            "INSERT INTO $table(created_at,code,locale,title,is_default) " .
            "VALUES
            ('$nowdate','mn','mn-MN','Монгол',1),
            ('$nowdate','en','en-US','English',0)";
        $this->exec($query);
    }

    /**
     * Хэл шинээр бүртгэх (INSERT).
     *
     * created_at утга дамжаагүй бол автоматаар системийн огноо нэмнэ.
     *
     * @param array $record  Оруулах өгөгдлийн массив
     * @return array|false   Амжилттай бол оруулсан мөр, эсвэл false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }

    /**
     * id дугаараар хэлний мэдээлэл шинэчлэх (UPDATE).
     *
     * updated_at утга дамжаагүй бол автоматаар системийн огноог өгнө.
     *
     * @param int   $id      Шинэчлэх мөрийн дугаар
     * @param array $record  Шинэчлэгдэх талбарууд
     * @return array|false   Амжилттай бол шинэчлэгдсэн мөр, эсвэл false
     */
    public function updateById(int $id, array $record): array|false
    {
        $record['updated_at'] ??= \date('Y-m-d H:i:s');
        return parent::updateById($id, $record);
    }
}
