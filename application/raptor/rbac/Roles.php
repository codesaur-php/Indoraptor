<?php

namespace Raptor\RBAC;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Roles - RBAC роль (эрхийн бүлэг) хадгалах модель.
 *
 * RBAC архитектур:
 * ───────────────────────────────────────────────────────────────
 *  - Permission  = Хэрэглэгч юу хийх эрхтэй вэ?
 *  - Role        = Олон permission-ийг нэгтгэсэн бүлэг
 *  - UserRole    = Хэрэглэгч → Role холболт
 *
 * Энэхүү Roles хүснэгт нь системийн бүх “эрхийн бүлэг”-ийг хадгална.
 * Жишээ:
 *   - admin
 *   - editor
 *   - manager
 *   - viewer
 *   - coder (framework-ийн “super admin”)
 *
 * Хүснэгтийн баганууд:
 * ───────────────────────────────────────────────────────────────
 * id           - bigint, primary key
 *
 * name         - varchar(128), UNIQUE, not null  
 *                Ролийн machine name (жишээ: "coder", "admin")
 *
 * description  - varchar(255)  
 *                Ролийн тайлбар (UI-д ашиглагдаж болно)
 *
 * alias        - varchar(64), not null  
 *                Ролийн ангилал (жишээ: "system", "general")
 *
 * created_at   - datetime  
 * created_by   - FK → users.id  
 *                 Ролийг үүсгэсэн хэрэглэгч
 *
 *
 * __initial(): анхны роль үүсгэх (seed)
 * ───────────────────────────────────────────────────────────────
 * Хүснэгт шинээр үүсэх үед систем автоматаар дараах ролиудыг устгана:
 *
 *   - coder (alias="system")
 *     → Framework-ийн super-user
 *     → Бүх permission-ийг эзэмших эрхтэй
 *     → Системийн хөгжил, засвар, конфигурацид ашиглагдана
 *
 * Энэ роль нь RBAC-н хамгийн өндөр түвшний эрх.
 *
 *
 * Security онцлогууд:
 * ───────────────────────────────────────────────────────────────
 * - name нь unique → давхардсан роль үүсэхгүй
 * - coder rôle нь системийн "root super admin"
 * - created_by FK → audit trail хадгална
 *
 *
 * Data integrity:
 * ───────────────────────────────────────────────────────────────
 * - created_at автоматаар тохируулагдана
 * - FK created_by → users.id
 *      ON DELETE SET NULL
 *      ON UPDATE CASCADE
 *
 */
class Roles extends Model
{
    /**
     * Roles модель үүсэх - хүснэгтийн бүтэц болон багануудыг тодорхойлох.
     *
     * @param \PDO $pdo  PDO instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id',          'bigint'))->primary(),
           (new Column('name',        'varchar', 128))->unique()->notNull(),
            new Column('description', 'varchar', 255),
           (new Column('alias',       'varchar', 64))->notNull(),
            new Column('created_at',  'datetime'),
            new Column('created_by',  'bigint')
        ]);

        $this->setTable('rbac_roles');
    }

    /**
     * __initial() - Roles хүснэгтийг анх үүсгэх үед FK болон анхны өгөгдөл үүсгэх.
     *
     * FK:
     *   rbac_roles.created_by → users.id
     *        ON DELETE SET NULL
     *        ON UPDATE CASCADE
     *
     * Seed:
     *   coder - system super-admin роль.
     *
     * Тайлбар:
     *   coder роль нь Indoraptor framework-д бүх модулийг
     *   удирдах боломжтой “дээд түвшний эрх” юм.
     *   Энэ рольгүй бол системийн.permission setup дамжих боломжгүй.
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
            // users хүснэгтийн нэрийг UsersModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
            // FK created_by → users.id
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

        // Default системийн роль (super admin)
        $nowdate = \date('Y-m-d H:i:s');
        $query =
            "INSERT INTO $table(created_at, name, description, alias)
             VALUES('$nowdate', 'coder', 'Coder can do anything!', 'system')";
        $this->exec($query);
    }

    /**
     * insert() - Role үүсгэх үед created_at автоматаар тохируулах.
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
