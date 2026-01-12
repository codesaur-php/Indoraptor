<?php

namespace Raptor\RBAC;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * UserRole - RBAC системийн "Хэрэглэгч ↔ Роль" (Many-to-Many) холболтын модель.
 *
 * RBAC архитектур дахь байр суурь:
 * ───────────────────────────────────────────────────────────────
 *  - Permission     = нэгж эрх
 *  - Role           = эрхийн багц
 *  - RolePermission = Role → Permission холболт
 *  - UserRole       = Хэрэглэгч → Role холболт  ← (энэ хүснэгт)
 *
 * Энэхүү хүснэгт нь:
 *    → Хэрэглэгч ямар рольтой вэ?
 *    → Нэг хэрэглэгч хэд хэдэн рольтой байж болох уу? (Тийм)
 *    → Роль уствал холбогдох бүх хэрэглэгчийн холболт устах уу? (Тийм)
 *
 * Хүснэгтийн баганууд:
 * ───────────────────────────────────────────────────────────────
 * id           - bigint, primary key
 *
 * user_id      - FK → users.id  
 *                Хэрэглэгчийн дугаар
 *
 * role_id      - FK → rbac_roles.id  
 *                Хэрэглэгчид оноосон роль
 *
 * created_at   - datetime  
 * created_by   - FK → users.id  
 *                Энэ холболтыг хэн үүсгэсэн бэ (audit trail)
 *
 *
 * __initial(): Анхны seed өгөгдөл
 * ───────────────────────────────────────────────────────────────
 * Framework анх ажиллах үед:
 *   → user_id = 1 хэрэглэгч
 *   → role_id = 1 (coder - super admin role)
 *
 * Автоматаар холбогдоно.
 *
 * Энэ нь:
 *   - Анхны системийн админ эрх
 *   - RBAC UI-д нэвтрэх эрх
 *   - Permission удирдах эрхтэй
 *
 * болох **суурь эрх** юм.
 *
 *
 * Cascade rules:
 * ───────────────────────────────────────────────────────────────
 * user_id → users.id  
 *    ON DELETE CASCADE  
 *    → Хэрэглэгч уствал түүний бүх role mapping устна.
 *
 * role_id → rbac_roles.id  
 *    ON DELETE CASCADE  
 *    → Роль уствал түүнийг авсан бүх хэрэглэгчдийн холболт устна.
 *
 * created_by → users.id  
 *    ON DELETE SET NULL  
 *    → Audit лог хадгалах зорилгоор created_by null болно.
 *
 *
 * Security онцлог:
 * ───────────────────────────────────────────────────────────────
 * - Нэг хэрэглэгч олон роль авна → олон permission inherit хийнэ
 * - RolePermission + UserRole нийлж хэрэглэгчийн full permission list бүрддэг
 * - Initial seed → super admin заавал байх ёстой
 *
 */
class UserRole extends Model
{
    /**
     * Модель constructor - багана болон хүснэгтийн нэр тодорхойлох.
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id',         'bigint'))->primary(),
           (new Column('user_id',    'bigint'))->notNull(),
           (new Column('role_id',    'bigint'))->notNull(),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
        ]);

        $this->setTable('rbac_user_role');
    }

    /**
     * Тухайн хэрэглэгчид оноосон бүх роль (role_id) жагсаалтыг авах.
     *
     * Формат:
     *   [
     *      ['id' => X, 'role_id' => 2],
     *      ['id' => Y, 'role_id' => 4],
     *      ...
     *   ]
     *
     * Давуу тал:
     *   - Role-д хамаарах permission-ийг fetch хийхэд ашиглагддаг
     *   - Нэг хэрэглэгч олон рольтой байж болно
     *
     * @param int $user_id
     * @return array
     */
    public function fetchAllRolesByUser(int $user_id): array
    {
        return $this->query(
            "SELECT id, role_id
               FROM {$this->getName()}
              WHERE user_id = $user_id"
        )->fetchAll();
    }

    /**
     * __initial() - Хүснэгт шинээр үүсэх үед FK constraint-үүд болон анхны
     * супер админ холболтыг (user_id=1 → role_id=1) үүсгэнэ.
     *
     * Анхны seed:
     *   user_id = 1
     *   role_id = 1  (coder роль)
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

            // Хүснэгтийн нэрийг Roles болон UsersModel-ийн getName() метод ашиглан динамикаар авна.
            // Ирээдүйд хүснэгтийн нэр өөрчлөгдвөл Model класс дахь setTable() засах хангалттай.
            $roles = (new Roles($this->pdo))->getName();
            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
            // FK: user_id → users.id
            $this->exec("
                ALTER TABLE $table
                ADD CONSTRAINT {$table}_fk_user_id
                FOREIGN KEY (user_id)
                REFERENCES $users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ");
            // FK: role_id → rbac_roles.id
            $this->exec("
                ALTER TABLE $table
                ADD CONSTRAINT {$table}_fk_role_id
                FOREIGN KEY (role_id)
                REFERENCES $roles(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ");
            // FK: created_by → users.id
            $this->exec("
                ALTER TABLE $table
                ADD CONSTRAINT {$table}_fk_created_by
                FOREIGN KEY (created_by)
                REFERENCES $users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
            ");

            $this->setForeignKeyChecks(true);
        }

        // Анхны супер админ холболт
        $nowdate = \date('Y-m-d H:i:s');
        $query = "INSERT INTO $table(created_at, user_id, role_id)
                  VALUES('$nowdate', 1, 1)";
        $this->exec($query);
    }

    /**
     * insert() - created_at автоматаар тохируулах.
     *
     * @param array $record
     * @return array|false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }
}
