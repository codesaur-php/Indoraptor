<?php

namespace Raptor\Content;

use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

/**
 * Class SettingsModel
 *
 * Indoraptor framework-ийн **Settings** (сайтын ерөнхий тохиргоо) хадгалах model.
 *
 * - `raptor_settings` хүснэгт дээр ажиллана
 * - LocalizedModel ашиглаж байгаа тул:
 *   - `setColumns()` → үндсэн хүснэгтийн баганууд
 *   - `setContentColumns()` → хэл тус бүрийн контент (title, description, address г.м)
 *
 * Гол хэрэглээ:
 * - Админы удирдлагын хэсэгт:
 *   - Сайтын гарчиг, лого, тайлбар
 *   - Холбоо барих мэдээлэл (утас, имэйл, хаяг)
 *   - Favicon, Apple Touch Icon
 *   - Нэмэлт config JSON / TEXT
 * - `retrieve()` функцээр хамгийн сүүлд идэвхтэй (`is_active=1`) бичлэгийг авах
 *
 * Анхаарах зүйл:
 * - `created_by`, `updated_by` нь Indoraptor-ийн хэрэглэгчийн хүснэгт
 *   (`Raptor\User\UsersModel`) рүү FK холболттой.
 */
class SettingsModel extends LocalizedModel
{
    /**
     * SettingsModel constructor.
     *
     * @param \PDO $pdo
     *      Indoraptor / codesaur DataObject-той хамт ашиглах PDO instance.
     *      - DB холболтыг гаднаас Injection хэлбэрээр авна.
     *      - PDOTrait-ээр дамжин кеш, driver name, FK toggle г.м ашиглагдана.
     *
     * Хийж буй ажил:
     *  - `$this->setInstance($pdo)` → LocalizedModel-д PDO-г суулгах
     *  - `$this->setColumns([...])` → үндсэн хүснэгтийн бүтэц тодорхойлох
     *  - `$this->setContentColumns([...])` → хэл тус бүрийн контент тодорхойлох
     *  - `$this->setTable('raptor_settings')` → үндсэн хүснэгтийн нэр оноох
     */
    public function __construct(\PDO $pdo)
    {
        // Indoraptor DataObject-ийн PDO instance-г тохируулна
        $this->setInstance($pdo);

        // Үндсэн (primary / non-localized) хүснэгтийн баганууд
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),          // PK, авто өсөх ID
            new Column('email', 'varchar', 70),              // Ерөнхий контакт имэйл
            new Column('phone', 'varchar', 70),              // Ерөнхий холбоо барих утас
            new Column('favico', 'varchar', 255),            // Favicon файлын харгалзах зам
            new Column('apple_touch_icon', 'varchar', 255),  // Apple touch icon зам
            new Column('config', 'text'),                    // Нэмэлт тохиргоо (ихэвчлэн JSON)
           (new Column('is_active', 'tinyint'))->default(1), // Тухайн мөр идэвхтэй эсэх (1=идэвхтэй)
            new Column('created_at', 'datetime'),            // Бичлэг үүсгэсэн огноо
            new Column('created_by', 'bigint'),              // Үүсгэсэн хэрэглэгчийн ID (FK)
            new Column('updated_at', 'datetime'),            // Сүүлд шинэчилсэн огноо
            new Column('updated_by', 'bigint')               // Шинэчилсэн хэрэглэгчийн ID (FK)
        ]);

        // Хэл тус бүрийн контент хадгалах баганууд (Localized / content table)
        $this->setContentColumns([
            new Column('title', 'varchar', 70),              // Веб сайтын гарчиг (title)
            new Column('logo', 'varchar', 255),              // Лого зураг / зам
            new Column('description', 'varchar', 255),       // Товч тайлбар / SEO description
            new Column('urgent', 'text'),                    // Яаралтай мэдэгдэл / banner текст
            new Column('contact', 'text'),                   // Холбоо барих дэлгэрэнгүй мэдээлэл (HTML байж болно)
            new Column('address', 'text'),                   // Хаяг (олон мөрт текст)
            new Column('copyright', 'varchar', 255)          // Зохиогчийн эрхийн мөр (footer)
        ]);

        // Үндсэн хүснэгтийн нэр
        $this->setTable('raptor_settings');
    }

    /**
     * Хүснэгтийг анх удаа үүсгэх үед ажиллах hook.
     *
     * LocalizedModel / DataObject доторх автомат миграцийн үед:
     * - FK constraint-уудыг үүсгэх
     * - Зарим DB specific тохиргоонуудыг хийх зорилготой.
     *
     * Энд:
     *  - `created_by` → users.id рүү FK
     *  - `updated_by` → users.id рүү FK
     *  - FK fail болохоос сэргийлж түр хугацаанд foreign_key_checks-ийг унтраана.
     *
     * @return void
     */
    protected function __initial(): void
    {
        $table = $this->getName();

        // SQLite дээр ALTER TABLE ... ADD CONSTRAINT дэмжигддэггүй
        // MySQL/PostgreSQL дээр л FK constraint нэмнэ
        if ($this->getDriverName() != 'sqlite') {
            // FK constraint нэмэхийн өмнө FK шалгалтыг түр унтраана
            $this->setForeignKeyChecks(false);

            // Raptor\User\UsersModel-ийн хүснэгтийн нэрийг авах
            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();

            // created_by талбарын FK
            $this->exec(
                "ALTER TABLE $table 
                 ADD CONSTRAINT {$table}_fk_created_by 
                 FOREIGN KEY (created_by) REFERENCES $users(id) 
                 ON DELETE SET NULL 
                 ON UPDATE CASCADE"
            );

            // updated_by талбарын FK
            $this->exec(
                "ALTER TABLE $table 
                 ADD CONSTRAINT {$table}_fk_updated_by 
                 FOREIGN KEY (updated_by) REFERENCES $users(id) 
                 ON DELETE SET NULL 
                 ON UPDATE CASCADE"
            );

            // FK шалгалтыг дахин идэвхжүүлнэ
            $this->setForeignKeyChecks(true);
        }
    }

    /**
     * Шинэ settings бичлэг оруулах.
     *
     * @param array $record
     *      Үндсэн хүснэгтийн өгөгдөл:
     *      - email, phone, favico, apple_touch_icon, config, is_active, created_by г.м
     * @param array $content
     *      Хэл тус бүрийн контент:
     *      - title, logo, description, urgent, contact, address, copyright
     *      - LocalizedModel-ийн форматтай (жишээ нь: ['mn_MN' => [...], 'en_US' => [...]] )
     *
     * @return array|false
     *      - Амжилттай байвал:
     *          [
     *              'record'  => [...], // үндсэн мөр
     *              'content' => [...]  // контент мөрүүд
     *          ]
     *      - Амжилтгүй бол `false`
     *
     * Тайлбар:
     *  - Хэрэв `$record['created_at']` ирээгүй бол автоматаар одоогийн цагийг онооно.
     *  - Дараа нь `parent::insert()` дуудагдана (LocalizedModel).
     */
    public function insert(array $record, array $content): array|false
    {
        // created_at ирээгүй бол автомат огноо онооно
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record, $content);
    }

    /**
     * ID-р settings бичлэг шинэчлэх.
     *
     * @param int   $id
     *      Шинэчлэх гэж буй үндсэн мөрийн ID (`raptor_settings.id`)
     * @param array $record
     *      Үндсэн хүснэгтийн шинэ утгууд:
     *      - phone, email, config, is_active, updated_by г.м
     * @param array $content
     *      Хэл тус бүрийн шинэ контент:
     *      - title, description, logo, address, contact г.м
     *
     * @return array|false
     *      - Амжилттай бол шинэчлэгдсэн өгөгдөлтэй массив
     *      - Алдаа эсвэл олдоогүй бол `false`
     *
     * Тайлбар:
     *  - `$record['updated_at']` параметр ирээгүй бол автоматаар одоогийн цаг онооно.
     *  - LocalizedModel-ийн `updateById()`-ыг ашиглаж, үндсэн + контент хүснэгтийг зэрэг шинэчилнэ.
     */
    public function updateById(int $id, array $record, array $content): array|false
    {
        // updated_at ирээгүй тохиолдолд автоматаар одоогийн цаг онооно
        $record['updated_at'] ??= \date('Y-m-d H:i:s');        
        return parent::updateById($id, $record, $content);
    }

    /**
     * Идэвхтэй settings тохиргоог авах.
     *
     * @return array
     *      - `is_active=1` нөхцөлтэй мөрүүдээс хамгийн сүүлийнхийг нь буцаана
     *      - Хоосон байвал хоосон массив `[]`
     *
     * Тайлбар:
     *  - `getRows(['WHERE' => 'p.is_active=1'])` нь:
     *      - `p` нь ихэвчлэн primary table-ийн alias (LocalizedModel дотор)
     *      - Хэрэв олон идэвхтэй бичлэг байвал `end($record)` ашиглаж хамгийн
     *        сүүлд орсон/уншсан мөрийг буцаана.
     *  - UI талд: 
     *      - header, footer, contact page, SEO мета мэдээлэл г.м бүх газар
     *        яг энэ методыг ашиглаж ерөнхий тохиргоог унших боломжтой.
     */
    public function retrieve(): array
    {
        $record = $this->getRows(['WHERE' => 'p.is_active=1']) ?? [];
        // Хоосон байвал [] буцаана, байвал хамгийн сүүлийнх мөрийг буцаана
        return \end($record) ?: [];
    }
}
