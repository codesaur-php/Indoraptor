<?php

namespace Raptor\Organization;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class OrganizationModel
 *
 * Байгууллагын (`organizations`) хүснэгттэй ажиллах өгөгдлийн загвар (Model) класс.
 * Энэ класс нь Indoraptor Framework-ийн DataObject\Model-ийг ашиглан
 * хүснэгтийн бүтцийг тодорхойлох, CRUD болон өгөгдлийн агуулахтай
 * уялдаа холбоо үүсгэх үүрэгтэй.
 *
 * Үндсэн боломжууд:
 *  - Байгууллагын хүснэгтийн багануудыг тодорхойлох (id, parent_id, name, logo …)
 *  - FK constraint-уудыг анхны тохиргоонд үүсгэх
 *  - Байгууллагын боломжит эцэг байгууллагуудын жагсаалт авах
 *  - Шинэ бичлэг үүсгэх үед created_at талбарыг автоматаар бөглөх
 *
 * Хүснэгт ашиглалт:
 *  - Байгууллага → Байгууллага (parent_id)
 *  - Байгууллага → Хэрэглэгч (created_by, updated_by)
 *
 * @package Raptor\Organization
 */
class OrganizationModel extends Model
{
    /**
     * OrganizationModel constructor.
     *
     * @param \PDO $pdo PDO instance - мэдээллийн сантай шууд холбогдоно.
     *
     * Конструктор дотор:
     *  - Мэдээллийн сангийн холболтыг тохируулна
     *  - Байгууллагын хүснэгтийн багануудыг тодорхойлно
     *  - Хүснэгт ашиглах нэрийг `organizations` гэж онооно
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        // Байгууллагын хүснэгтийн багануудын тодорхойлолт
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),                 // Давтагдашгүй ID (PK)
            new Column('parent_id', 'bigint'),                      // Эцэг байгууллагын ID
           (new Column('name', 'varchar', 255))->unique(),          // Байгууллагын нэр (давтагдахгүй)
            new Column('logo', 'varchar', 255),                     // Лого - URL/path
            new Column('logo_file', 'varchar', 255),                // Лого файл зам
            new Column('logo_size', 'int'),                         // Лого файлын хэмжээ (byte-ээр)
           (new Column('alias', 'varchar', 64))->default('common'), // Байгууллагын төрөл/нэршил
           (new Column('is_active', 'tinyint'))->default(1),        // Идэвхтэй эсэх
            new Column('created_at', 'datetime'),                   // Үүсгэсэн огноо
            new Column('created_by', 'bigint'),                     // Үүсгэсэн хэрэглэгч
            new Column('updated_at', 'datetime'),                   // Сүүлд зассан огноо
            new Column('updated_by', 'bigint')                      // Сүүлд зассан хэрэглэгч
        ]);

        // Хүснэгтийн нэр: organizations
        $this->setTable('organizations');
    }
    
    /**
     * Байгууллагын боломжит эцэг байгууллагуудын жагсаалтыг авах.
     *
     * parent_id нь NULL эсвэл 0 бол тухайн байгууллагыг "эцэг байгууллага" гэж үзнэ.
     * is_active = 1 → зөвхөн идэвхтэй байгууллагууд
     *
     * @return array Эцэг байгууллагуудын мөрүүдийн жагсаалт
     */
    public function fetchAllPotentialParents(): array
    {
        return $this->query(
            "SELECT * FROM {$this->getName()} 
             WHERE (parent_id = 0 OR parent_id IS NULL) 
               AND is_active = 1"
        )->fetchAll();
    }

    /**
     * Анхны тохиргоо (__initial):
     *
     * - Foreign Key шалгалтыг түр хааж өгнө
     * - created_by / updated_by багануудыг Users хүснэгттэй FK холбоо үүсгэнэ
     * - Анхны өгөгдөл (System байгууллага) автоматаар нэмнэ
     *
     * Анх удаа хүснэгт үүсэх үед DataObject систем энэ функцийг автоматаар дуудна.
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();

        // SQLite нь ALTER TABLE ... ADD CONSTRAINT дэмжихгүй
        // MySQL/PostgreSQL дээр л FK constraint нэмнэ
        if ($this->getDriverName() != 'sqlite') {
            // FK шалгалтыг түр хаах
            $this->setForeignKeyChecks(false);

            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();

            // created_by → users.id FK
            $this->exec(
                "ALTER TABLE $table 
                 ADD CONSTRAINT {$table}_fk_created_by 
                 FOREIGN KEY (created_by) 
                 REFERENCES $users(id) 
                 ON DELETE SET NULL 
                 ON UPDATE CASCADE"
            );

            // updated_by → users.id FK
            $this->exec(
                "ALTER TABLE $table 
                 ADD CONSTRAINT {$table}_fk_updated_by 
                 FOREIGN KEY (updated_by) 
                 REFERENCES $users(id) 
                 ON DELETE SET NULL 
                 ON UPDATE CASCADE"
            );

            // FK шалгалтыг буцааж идэвхжүүлэх
            $this->setForeignKeyChecks(true);
        }

        // Анхны өгөгдөл: System байгууллага үүсгэх
        $nowdate = \date('Y-m-d H:i:s');
        $this->exec("INSERT INTO $table(created_at, name, alias) 
                     VALUES('$nowdate', 'System', 'system')");
    }

    /**
     * Байгууллага шинээр бүртгэх (INSERT).
     *
     * created_at утга дамжаагүй бол автоматаар системийн огноо нэмнэ.
     *
     * @param array $record Нэмэх гэж буй мөрийн өгөгдөл
     * @return array|false Амжилттай бол үүссэн мөрийн өгөгдөл, алдаатай бол false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }
}
