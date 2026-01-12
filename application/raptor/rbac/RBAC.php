<?php

namespace Raptor\RBAC;

/**
 * RBAC - Runtime-д хэрэглэгчийн бүх роль болон permission-ийг нэгтгэж,
 * эрх шалгах (authorization check) зориулалттай гол RBAC компонент.
 *
 * RBAC архитектур дахь байр суурь:
 * ───────────────────────────────────────────────────────────────
 *  Permission (нэгж эрх)
 *  Role (эрхийн багц)
 *  RolePermission  → Роль ямар permission-тэй вэ?
 *  UserRole        → Хэрэглэгч ямар рольтой вэ?
 *
 *  → RBAC.php      → Runtime-д хэрэглэгчийн эрхийг нэгтгэх “core”
 *
 * Энэ класс нь:
 *   - Хэрэглэгчид оноосон бүх роль (UserRole)
 *   - Тэдгээр рольд хамаарах бүх permission (RolePermission)
 *   → бүгдийг memory-д ачаалж, хурдан шалгах боломжтой болгож өгнө.
 *
 * Permission болон Role key:
 * ───────────────────────────────────────────────────────────────
 *  Role key        : "{alias}_{role_name}"
 *  Permission key  : "{alias}_{permission_name}"
 *
 * Жишээ:
 *   system_coder
 *   user_user_insert
 *   content_content_publish
 *
 * Давуу тал:
 *   - Хайлт O(1)
 *   - RT-аар маш хурдан ажиллана
 *   - Controller-оос $user->can('content_insert') гэх мэтээр шууд ашиглана
 *
 *
 * JSON Serialization:
 * ───────────────────────────────────────────────────────────────
 *  jsonSerialize() нь бүх роль болон permission-үүдийг JSON хэлбэрт хөрвүүлнэ.
 *  Энэ нь:
 *    - UI талд permission matrix харахад
 *    - Debugging
 *    - API response-д эрхийн мэдээлэл өгөхөд ашиглагдана.
 *
 */
class RBAC implements \JsonSerializable
{
    /**
     * Хэрэглэгчийн бүх роль
     *
     * Формат:
     *   [
     *      'system_coder' => Role object,
     *      'user_admin'   => Role object,
     *   ]
     *
     * @var array<string,Role>
     */
    public array $role = [];

    /**
     * Constructor - хэрэглэгчийн бүх роль болон permission-үүдийг memory-д ачаална.
     *
     * Процесс:
     * ───────────────────────────────────────────────────────────────
     * 1) UserRole хүснэгтээс хэрэглэгчийн бүх role_id-г авна.
     * 2) Role хүснэгтээс alias + name мэдээллийг авна.
     * 3) Role.php → fetchPermissions() ашиглан тухайн рольд хамаарах permission-үүдийг ачаална.
     * 4) Role object-уудыг $this->role[] массивт хадгална.
     *
     * @param \PDO $pdo
     * @param int  $user_id
     */
    public function __construct(\PDO $pdo, int $user_id)
    {
        // Хүснэгтийн нэрийг Roles болон UserRole-ийн getName() метод ашиглан динамикаар авна.
        // Ирээдүйд хүснэгтийн нэр өөрчлөгдвөл Model класс дахь setTable() засах хангалттай.
        $roles_table       = (new Roles($pdo))->getName();
        $user_role_table   = (new UserRole($pdo))->getName();
        $sql =
            "SELECT t1.role_id, t2.name, t2.alias
               FROM $user_role_table t1
               INNER JOIN $roles_table t2
                       ON t1.role_id = t2.id
              WHERE t1.user_id = :user_id";
        $pdo_stmt = $pdo->prepare($sql);

        if ($pdo_stmt->execute([':user_id' => $user_id])) {
            while ($row = $pdo_stmt->fetch()) {
                // "alias_name" форматтай роль key
                $roleKey = "{$row['alias']}_{$row['name']}";
                // Тухайн рольд хамаарах permission-үүдийг fetch хийх
                $this->role[$roleKey] =
                    (new Role())->fetchPermissions($pdo, $row['role_id']);
            }
        }
    }

    /**
     * Хэрэглэгч тодорхой рольтой эсэхийг шалгах.
     *
     * Жишээ:
     *   $rbac->hasRole('system_coder');
     *
     * @param string $roleName "{alias}_{name}" форматтай
     * @return bool
     */
    public function hasRole(string $roleName): bool
    {
        foreach (\array_keys($this->role) as $name) {
            if ($name === $roleName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Хэрэглэгч тодорхой permission-тэй эсэхийг шалгах.
     *
     * Хоёр горимтой:
     * ───────────────────────────────────────────────────────────────
     * 1) roleName заагдсан бол → зөвхөн тухайн роль дотор хайна
     * 2) roleName null бол → хэрэглэгчийн бүх роль дотор хайна
     *
     * Жишээ:
     *   $rbac->hasPrivilege('user_user_insert');            // бүх рольд хайна
     *   $rbac->hasPrivilege('content_publish', 'editor');   // зөвхөн editor рольд
     *
     * @param string      $permissionName "{alias}_{name}" форматтай permission key
     * @param string|null $roleName       "{alias}_{name}" форматтай роль key
     * @return bool
     */
    public function hasPrivilege(string $permissionName, ?string $roleName = null): bool
    {
        if (isset($roleName)) {
            // Зөвхөн тодорхой роль дотор шалгах
            if (isset($this->role[$roleName])) {
                return $this->role[$roleName]->hasPermission($permissionName);
            }
            return false;
        }

        // Бүх роль дотор хайх
        foreach ($this->role as $role) {
            if ($role->hasPermission($permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * JSON сериализаци - Хэрэглэгчийн эрхийн бүтцийг JSON болгон хөрвүүлнэ.
     *
     * Формат:
     * ───────────────────────────────────────────────────────────────
     *  {
     *      "system_coder": {
     *          "system_logger": true,
     *          "user_user_insert": true,
     *          ...
     *      },
     *      "user_admin": {
     *          "user_user_update": true,
     *          ...
     *      }
     *  }
     *
     * UI permission matrix, debugging, API return-д ашиглагдана.
     *
     * @return mixed JSON-д хөрвөх бүтэц
     */
    public function jsonSerialize(): mixed
    {
        $role_permissions = [];
        foreach ($this->role as $name => $role) {
            if (!$role instanceof Role) {
                continue;
            }

            $role_permissions[$name] = [];
            foreach ($role->permissions as $permission => $granted) {
                $role_permissions[$name][$permission] = $granted;
            }
        }
        return $role_permissions;
    }
}
