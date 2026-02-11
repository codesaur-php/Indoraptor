<?php

namespace Raptor\Authentication;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

\define('CODESAUR_PASSWORD_RESET_MINUTES', (int) ($_ENV['CODESAUR_PASSWORD_RESET_MINUTES'] ?? 10));

/**
 * Class ForgotModel
 *
 * Нууц үг сэргээх хүсэлтүүдийг (forgot password requests) хадгалах
 * зориулалттай дата модел. Хэрэглэгч нууц үгээ мартсан үед үүсдэг
 * UUID-тэй сэргээх холбоос, баталгаажуулах код, IP хаяг, timestamp
 * зэрэг мэдээллийг энэ хүснэгтэд бүртгэнэ.
 *
 * Энэхүү модел нь DataObject\Model-ийн боломжуудыг ашиглан:
 *  - багана (column) тодорхойлох
 *  - анхдагч түлхүүр, unique талбар, default утга заах
 *  - created_at / updated_at автоматаар тохируулах
 *  - хэрэглэгч (users) хүснэгттэй гадаад түлхүүртэй холбох
 * зэрэг үйлдлүүдийг гүйцэтгэнэ.
 *
 * @package Raptor\Authentication
 */
class ForgotModel extends Model
{
    /**
     * ForgotModel constructor.
     *
     * @param \PDO $pdo
     *      Database connection (PDO instance).
     *
     * Конструктор нь Forgot хүснэгтийн бүх баганыг тодорхойлж,
     * моделийн метадата-г бүрдүүлнэ.
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        // Forgot хүснэгтийн багануудын бүтэц
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('forgot_password', 'varchar', 255))->unique(),
            new Column('user_id', 'bigint'),
            new Column('username', 'varchar', 255),
            new Column('first_name', 'varchar', 255),
            new Column('last_name', 'varchar', 255),
            new Column('email', 'varchar', 128),
            new Column('remote_addr', 'varchar', 46),  // IPv4/IPv6
            new Column('code', 'varchar', 2),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
            new Column('updated_at', 'datetime')
        ]);
        
        // Хүснэгтийн нэр
        $this->setTable('forgot');
    }
    
    /**
     * __initial()
     *
     * Моделийн хүснэгт шинээр үүсэх үед автоматаар дуудагдах hook.
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
            
            // Энд гадаад түлхүүр (FOREIGN KEY)-ийн холбоосыг UsersModel-ийн хүснэгттэй үүсгэнэ.
            // users хүснэгтийн нэрийг UsersModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
            // user_id → {UsersModel::getName()}(id)
            // ON DELETE SET NULL → Хэрэглэгч устсан тохиолдолд user_id null болно.
            // ON UPDATE CASCADE → Хэрэглэгчийн id өөрчлөгдвөл автоматаар шинэчлэгдэнэ.
            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();            
            $this->exec("
                ALTER TABLE $table 
                ADD CONSTRAINT {$table}_fk_user_id 
                FOREIGN KEY (user_id) 
                REFERENCES $users(id) 
                ON DELETE SET NULL 
                ON UPDATE CASCADE
            ");

            $this->setForeignKeyChecks(true);
        }
    }
    
    /**
     * insert()
     *
     * Нууц үг сэргээх шинэ бичлэг нэмэх.
     * created_at талбарыг заагаагүй бол автоматаар одоогийн огноо тавина.
     *
     * @param array $record
     *      Мэдээллийн массив (username, email, code, remote_addr, …)
     *
     * @return array|false
     *      Амжилттай бол оруулсан бичлэг, алдаа гарвал false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }
    
    /**
     * updateById()
     *
     * Бичлэгийн id ашиглан шинэчлэлт хийх.
     * updated_at талбар байхгүй бол автоматаар timestamp үүсгэнэ.
     *
     * @param int $id
     *      Засах бичлэгийн ID
     * @param array $record
     *      Засварын мэдээлэл
     *
     * @return array|false
     *      Амжилттай бол шинэчлэгдсэн бичлэг, алдаа гарвал false
     */
    public function updateById(int $id, array $record): array|false
    {
        $record['updated_at'] ??= \date('Y-m-d H:i:s');
        return parent::updateById($id, $record);
    }
}
