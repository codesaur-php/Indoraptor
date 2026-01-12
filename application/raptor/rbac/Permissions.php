<?php

namespace Raptor\RBAC;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Permissions - RBAC эрхийн системийн үндсэн модель.
 *
 * Энэ хүснэгт нь системийн бүх боломжит үйлдэл (permission)-ийг
 * нэр, alias, module, тайлбар зэрэг мета мэдээллийн хамт хадгалдаг.
 *
 * RBAC архитектур дахь үүрэг:
 * ───────────────────────────────────────────────────────────────
 *  - Permission: “юу хийх эрхтэй вэ?” (жишээ: user_insert, content_delete)
 *  - Role: Permission-үүдийн багц (жишээ: admin, editor, viewer)
 *  - UserRole: хэрэглэгч → role холболт
 *
 * Permissions хүснэгт нь системд ажиллах бүх эрхийн
 * жагсаалтын authoritative source болно.
 *
 *
 * Баганууд:
 * ───────────────────────────────────────────────────────────────
 * id            - bigint, primary key
 *
 * name          - string (128)  
 *                 Permission нэр (unique).  
 *                 Жишээ: "user_insert", "organization_delete"
 *
 * module        - string (128)  
 *                 Permission ямар модульд харьяалагдах.  
 *                 Жишээ: "user", "organization", "content"
 *
 * description   - string (255)  
 *                 Permission-ийн тайлбар (UI болон документацид хэрэглэгдэнэ)
 *
 * alias         - string (64), notNull  
 *                 Permission-ийн функционал ангилал.  
 *                 Жишээ: "system", "general"
 *
 * created_at    - datetime  
 * created_by    - FK → users.id  
 *                 Permission-г үүсгэсэн хэрэглэгч.  
 *
 *
 * __initial(): анхны Permission seed үүсгэнэ
 * ───────────────────────────────────────────────────────────────
 * Модель анх үүссэн үед (хүснэгт шинээр үүсэх үед) default permission-үүдийг
 * систем автоматаар бүртгэнэ.
 *
 * Эдгээр нь:
 *  - system logger permission
 *  - RBAC permissions
 *  - user management permissions
 *  - organization management permissions
 *  - content (page/news/file/settings) permissions
 *  - localization permissions
 *
 * Энэ нь framework-ийг суурилуулахад шаардлагатай үндсэн эрхүүдийг
 * автоматаар бүртгэх зориулалттай.
 *
 *
 * Security онцлогууд:
 * ───────────────────────────────────────────────────────────────
 *  - Permission name нь unique → давхардлаас хамгаалсан
 *  - created_by → users.id FK → audit trail
 *  - Seed permission-үүдийг хэтрүүлэн өөрчлөхөөс model хамгаална
 *  - Permissions хүснэгт нь хэрэглэгчидтэй шууд холбогдохгүй,
 *    role дээр дамжуулж хэрэглэгдэнэ
 *
 */
class Permissions extends Model
{
    /**
     * Permission модель үүсгэх - хүснэгт ба багануудыг тодорхойлох.
     *
     * @param \PDO $pdo  PDO instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id',          'bigint'))->primary(),
           (new Column('name',        'varchar', 128))->unique()->notNull(),
           (new Column('module',      'varchar', 128))->default('general'),
            new Column('description', 'varchar', 255),
           (new Column('alias',       'varchar', 64))->notNull(),
            new Column('created_at',  'datetime'),
            new Column('created_by',  'bigint')
        ]);

        $this->setTable('rbac_permissions');
    }

    /**
     * __initial() - Permission хүснэгт шинээр үүсэх үед FK болон анхны өгөгдөл үүсгэх hook.
     *
     * FK:
     *   rbac_permissions.created_by → users.id
     *       ON DELETE SET NULL
     *       ON UPDATE CASCADE
     *
     * Анхны seed өгөгдөл:
     *   - logger
     *   - rbac
     *   - user_* permissions
     *   - organization_* permissions
     *   - content_* permissions
     *   - localization_* permissions
     *
     * Анхны эрхүүд нь системийн үндсэн модулиудыг ажиллуулахад зайлшгүй
     * шаардлагатай тул автоматоор үүсгэнэ.
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

        // Seed үндсэн permission-үүд
        $nowdate = \date('Y-m-d H:i:s');
        $query =
            "INSERT INTO $table(created_at,alias,module,name,description)
            VALUES
            ('$nowdate','system','log','logger',''),
            ('$nowdate','system','user','rbac',''),
            ('$nowdate','system','user','user_index',''),
            ('$nowdate','system','user','user_insert',''),
            ('$nowdate','system','user','user_update',''),
            ('$nowdate','system','user','user_delete',''),
            ('$nowdate','system','user','user_organization_set',''),

            ('$nowdate','system','organization','organization_index',''),
            ('$nowdate','system','organization','organization_insert',''),
            ('$nowdate','system','organization','organization_update',''),
            ('$nowdate','system','organization','organization_delete',''),

            ('$nowdate','system','content','content_settings',''),
            ('$nowdate','system','content','content_index',''),
            ('$nowdate','system','content','content_insert',''),
            ('$nowdate','system','content','content_publish',''),
            ('$nowdate','system','content','content_delete',''),

            ('$nowdate','system','localization','localization_index',''),
            ('$nowdate','system','localization','localization_insert',''),
            ('$nowdate','system','localization','localization_update',''),
            ('$nowdate','system','localization','localization_delete','')
        ";
        $this->exec($query);
    }

    /**
     * insert() - Permission бүртгэх үед created_at автоматаар тохируулах.
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
