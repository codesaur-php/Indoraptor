<?php

namespace Raptor\Organization;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class OrganizationUserModel
 *
 * Байгууллага ↔ Хэрэглэгчийн холболтын хүснэгт (`organizations_users`)
 * дээр ажилладаг өгөгдлийн загвар (Model) класс.
 *
 * Энэ хүснэгт нь олон-тоо холбоос (many-to-many) хэлбэртэй бөгөөд:
 *  - Нэг хэрэглэгч олон байгууллагад харьяалагдаж болно
 *  - Нэг байгууллага олон хэрэглэгчтэй байж болно
 *
 * Үндсэн боломжууд:
 *  - Байгууллага-хэрэглэгчийн холболтын мөр үүсгэх
 *  - Тухайн хэрэглэгч тухайн байгууллагад харьяалагдсан эсэхийг шалгах
 *  - FK constraint-уудыг автоматаар тохируулах
 *  - created_at талбарыг автоматаар бүртгэх
 *
 * @package Raptor\Organization
 */
class OrganizationUserModel extends Model
{
    /**
     * OrganizationUserModel constructor.
     *
     * @param \PDO $pdo PDO instance - мэдээллийн сантай холбогдох объект
     *
     * Конструктор дотор:
     *  - organizations_users хүснэгтийн багануудыг тодорхойлно
     *  - хүснэгтийн нэрийг онооно
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        // Хүснэгтийн баганууд
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),  // Давтагдашгүй ID (PK)
            new Column('user_id', 'bigint'),         // Холбогдож буй хэрэглэгчийн ID
            new Column('organization_id', 'bigint'), // Холбогдож буй байгууллагын ID
            new Column('created_at', 'timestamp'),   // Холболт үүсгэсэн огноо
            new Column('created_by', 'bigint'),      // Энэ холболтыг үүсгэсэн хэрэглэгч
        ]);
        
        $this->setTable('organizations_users');
    }

    /**
     * Байгууллага-хэрэглэгчийн харьяаллыг шалгах.
     *
     * Тухайн хэрэглэгч тухайн байгууллагад харьяалагдсан эсэх,
     * мөн тухайн байгууллага идэвхтэй эсэхийг (is_active=1) хамт шалгана.
     *
     * @param int $organization_id Байгууллагын ID
     * @param int $user_id Хэрэглэгчийн ID
     *
     * @return array|false Тухайн мөр олдвол массив, олдохгүй бол false
     */
    public function retrieve(int $organization_id, int $user_id): array|false
    {
        $org_model = new OrganizationModel($this->pdo);

        $stmt = $this->prepare(
            'SELECT t1.* ' .
            "FROM {$this->getName()} t1 
             INNER JOIN {$org_model->getName()} t2 ON t1.organization_id = t2.id 
             WHERE t1.user_id = :user 
               AND t1.organization_id = :org 
               AND t2.is_active = 1 
             LIMIT 1"
        );

        $stmt->bindParam(':user', $user_id, \PDO::PARAM_INT);
        $stmt->bindParam(':org', $organization_id, \PDO::PARAM_INT);

        if ($stmt->execute() && $stmt->rowCount() === 1) {
            return $stmt->fetch();
        }

        return false;
    }

    /**
     * Анхны тохиргоо (__initial):
     *
     * - relation хүснэгтийн FK-үүдийг үүсгэнэ:
     *      user_id          → users(id)
     *      organization_id  → organizations(id)
     *      created_by       → users(id)
     *
     * - FK шалгалтыг түр унтрааж (setForeignKeyChecks(false)),
     *   тохиргоо хийсний дараа буцааж асаана
     *
     * - Анхны өгөгдөл: хэрэглэгч 1 → байгууллага 1 гэсэн харьяаллыг үүсгэнэ.
     *   Энэ нь системийн default холбоос бөгөөд супер админ байгууллагадаа багтах нөхцөл
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

            // Хүснэгтийн нэрийг UsersModel болон OrganizationModel-ийн getName() метод ашиглан динамикаар авна.
            // Ирээдүйд хүснэгтийн нэр өөрчлөгдвөл Model класс дахь setTable() засах хангалттай.
            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
            $organizations = (new OrganizationModel($this->pdo))->getName();

            // user_id → FK
            $this->exec(
                "ALTER TABLE $table 
                 ADD CONSTRAINT {$table}_fk_user_id 
                 FOREIGN KEY (user_id) 
                 REFERENCES $users(id) 
                 ON DELETE CASCADE 
                 ON UPDATE CASCADE"
            );

            // organization_id → FK
            $this->exec(
                "ALTER TABLE $table 
                 ADD CONSTRAINT {$table}_fk_organization_id 
                 FOREIGN KEY (organization_id) 
                 REFERENCES $organizations(id) 
                 ON DELETE CASCADE 
                 ON UPDATE CASCADE"
            );

            // created_by → FK
            $this->exec(
                "ALTER TABLE $table 
                 ADD CONSTRAINT {$table}_fk_created_by 
                 FOREIGN KEY (created_by) 
                 REFERENCES $users(id) 
                 ON DELETE SET NULL 
                 ON UPDATE CASCADE"
            );

            // FK буцааж асаах
            $this->setForeignKeyChecks(true);
        }

        // Анхны өгөгдөл - Super Admin → System Organization
        $nowdate = \date('Y-m-d H:i:s');
        $this->exec(
            "INSERT INTO $table(created_at, user_id, organization_id) 
             VALUES('$nowdate', 1, 1)"
        );
    }

    /**
     * Холболтын шинэ мөр бүртгэх (INSERT).
     *
     * created_at дамжаагүй бол автоматаар системийн огноогоор бүртгэнэ.
     *
     * @param array $record Нэмэх гэж буй өгөгдлийн мөр
     * @return array|false Амжилттай бол шинэ мөр, алдаатай бол false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }
}
