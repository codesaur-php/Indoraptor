<?php

namespace Raptor\Authentication;

/**
 * User - Нэвтэрсэн хэрэглэгчийн мэдээлэл болон RBAC эрхийн загвар.
 *
 * Энэ класс нь:
 *   - Хэрэглэгчийн профайл мэдээлэл (`profile`)
 *   - Нэвтэрсэн байгууллагын мэдээлэл (`organization`)
 *   - RBAC эрхүүд (`_rbac`) - Roles → Permissions матриц
 *
 * зэргийг агуулж, хэрэглэгч нь:
 *   · ямар рольтой вэ?  (is())
 *   · ямар үйлдэл хийх эрхтэй вэ? (can())
 *
 * гэдгийг төвлөрсөн байдлаар шалгах зориулалттай.
 *
 * RBAC бүтэц:
 * ───────────────────────────────────────────────────────────────
 *   _rbac = [
 *       'system_admin' => [
 *           'system_content_insert' => true,
 *           'system_content_delete' => true,
 *           ...
 *       ],
 *       'system_manager' => [
 *           'system_user_update' => true,
 *           ...
 *       ]
 *   ]
 *
 * "system_coder" роль:
 * ───────────────────────────────────────────────────────────────
 *   - Framework-ийн супер админ.
 *   - Хэрэв хэрэглэгч system_coder бол бүх role + permission = TRUE.
 *   - Энэ нь coder-д зориулагдсан дээд түвшний эрх.
 */
class User
{
    /** @var array Хэрэглэгчийн профайл (id, username, email, name ...) */
    public array $profile;

    /** @var array Нэвтэрсэн байгууллагын мэдээлэл (id, alias, name ...) */
    public array $organization;

    /**
     * @var array RBAC матриц
     *   - Key: role_name (жишээ: system_admin)
     *   - Value: [permission => true]
     */
    private readonly array $_rbac;

    /**
     * User constructor.
     *
     * @param array $user         Хэрэглэгчийн профайл
     * @param array $organization Нэвтэрсэн байгууллага
     * @param array $rbac         RBAC эрхүүдийн матриц (RBAC::jsonSerialize() гаралт)
     */
    public function __construct(array $user, array $organization, array $rbac)
    {
        $this->profile = $user;
        $this->organization = $organization;
        $this->_rbac = $rbac;
    }

    /**
     * Хэрэглэгч тодорхой рольтой эсэхийг шалгана.
     *
     * @param string $role  Жишээ: 'system_admin', 'content_manager'
     *
     * @return bool
     *
     * Тайлбар:
     * ───────────────────────────────────────────────────────────────
     *  - Хэрэв хэрэглэгч 'system_coder' бол бүх роль = TRUE.
     *  - Үгүй бол тухайн роль RBAC жагсаалтад байгаа эсэхийг шалгана.
     */
    public function is(string $role): bool
    {
        if (isset($this->_rbac['system_coder'])) {
            return true;
        }

        return isset($this->_rbac[$role]);
    }

    /**
     * Хэрэглэгч тодорхой permission-тэй эсэхийг шалгана.
     *
     * @param string      $permission  Зөвшөөрлийн нэр (жишээ: 'user_update')
     * @param string|null $role        Зөвхөн тодорхой роль дотор шалгах бол
     *                                 (жишээ: 'system_admin')
     *
     * @return bool
     *
     * Тайлбар:
     * ───────────────────────────────────────────────────────────────
     *   - 'system_coder' → бүх permission TRUE.
     *   - role заасан бол зөвхөн тухайн роль дотор permission шалгана.
     *   - role заагаагүй бол бүх ролоор давтаж нэг нь ч гэсэн TRUE байвал эрхтэй.
     */
    public function can(string $permission, ?string $role = null): bool
    {
        // Super admin: бүх эрхийг зөвшөөрнө
        if (isset($this->_rbac['system_coder'])) {
            return true;
        }

        // Заасан роль дотор permission шалгах
        if (!empty($role)) {
            return $this->_rbac[$role][$permission] ?? false;
        }

        // Бүх ролиос privilege хайх
        foreach ($this->_rbac as $role) {
            if (isset($role[$permission]) && $role[$permission] === true) {
                return true;
            }
        }

        return false;
    }
}
