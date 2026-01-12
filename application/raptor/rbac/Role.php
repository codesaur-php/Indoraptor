<?php

namespace Raptor\RBAC;

/**
 * Role - Runtime-д рольд харьяалагдах permission-үүдийг
 * ачаалах, шалгах зориулалттай lightweight объект.
 *
 * RBAC архитектур дахь энэ классын байр суурь:
 * ───────────────────────────────────────────────────────────────
 *  - Database: RolePermission + Permissions хүснэгтээс уншина.
 *  - Runtime: Controller → RBAC.php → User → Role
 *  - Purpose: hasPermission() ашиглан тухайн role тодорхой эрхтэй эсэхийг
 *              маш хурдан, DB query хийлгүйгээр шалгах.
 *
 * Permission keys:
 * ───────────────────────────────────────────────────────────────
 * Permission бүр дараах хэлбэрээр массивт хадгалагдана:
 *
 *      "{alias}_{name}"  => true
 *
 * Жишээ:
 *      system_logger              → системийн лог харах
 *      system_user_insert         → хэрэглэгч нэмэх эрх
 *      system_content_publish     → контент нийтлэх эрх
 *
 * Энэ формат нь:
 *    - alias (module/category)
 *    - name  (permission key)
 *
 * гэсэн RBAC стандарт бүтцийг давхар тусгаж өгсөн, хурдан хайлттай (O(1)) бүтэц.
 *
 */
class Role
{
    /**
     * Рольд харьяалагдах бүх permission-үүд
     *  → key: "{alias}_{name}"
     *  → value: true
     *
     * @var array<string,bool>
     */
    public array $permissions = [];

    /**
     * RolePermission болон Permissions хүснэгтээс тухайн role-д хамаарах
     * бүх permission-үүдийг уншиж, дотоод массивт хадгална.
     *
     * SQL Query:
     * ───────────────────────────────────────────────────────────────
     * SELECT t2.name, t2.alias
     *   FROM rbac_role_permission t1
     *   INNER JOIN rbac_permissions t2 ON t1.permission_id = t2.id
     *  WHERE t1.role_id = :role_id
     *
     * Ачаалалтын дараа:
     *   permissions["{$alias}_{$name}"] = true
     *
     * Давуу тал:
     *   - 1 удаагийн query → олон удаагийн hasPermission() шалгалт DB-гүй
     *   - Memory-д кешлэгдсэн тул permission шалгах хурд O(1)
     *
     * @param \PDO $pdo
     * @param int  $role_id  Role ID
     * @return $this
     */
    public function fetchPermissions(\PDO $pdo, int $role_id)
    {
        // Хүснэгтийн нэрийг Permissions болон RolePermission-ийн getName() метод ашиглан динамикаар авна.
        // Ирээдүйд хүснэгтийн нэр өөрчлөгдвөл Model класс дахь setTable() засах хангалттай.
        $permissions_table = (new Permissions($pdo))->getName();
        $role_perm_table   = (new RolePermission($pdo))->getName();
        $sql =
            "SELECT t2.name, t2.alias
               FROM $role_perm_table t1
               INNER JOIN $permissions_table t2
                       ON t1.permission_id = t2.id
              WHERE t1.role_id = :role_id";
        $pdo_stmt = $pdo->prepare($sql);
        if ($pdo_stmt->execute([':role_id' => $role_id])) {
            while ($row = $pdo_stmt->fetch()) {
                $key = "{$row['alias']}_{$row['name']}";
                $this->permissions[$key] = true;
            }
        }

        return $this;
    }

    /**
     * Тухайн рольд тодорхой permission байгаа эсэхийг шалгах.
     *
     * Хамгийн хурдан permission шалгалт:
     *   → memory-based array lookup
     *   → DB query огт хийгдэхгүй
     *
     * Ашиглах жишээ:
     * ───────────────────────────────────────────────────────────────
     *   $role->hasPermission("content_news_insert");
     *
     * @param string $permissionName "{alias}_{name}" форматтай permission key
     * @return bool
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions[$permissionName] ?? false;
    }
}
