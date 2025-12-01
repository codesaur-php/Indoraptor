<?php

namespace Raptor\RBAC;

use codesaur\Router\Router;

/**
 * RBACRouter - RBAC (Role-Based Access Control) модулийн бүх маршрут
 * болон HTTP endpoint-уудыг бүртгэх зориулалттай Router класс.
 *
 * RBAC архитектур дахь Router-ийн үүрэг:
 * ───────────────────────────────────────────────────────────────
 *  - RBACController руу чиглэгдсэн бүх UI/API замыг энд тодорхойлно.
 *  - Роль, permission, role-permission mapping зэрэг RBAC удирдлагын
 *    үндсэн CRUD болон тохиргооны endpoint-уудыг бүртгэнэ.
 *  - Dynamic route параметр `{alias}` ашиглан төрөл бүрийн RBAC бүлгийг
 *    (system, user, content, organization, гэх мэт) нэг controller-оор
 *    удирдах боломж олгоно.
 *
 * Ашиглагдсан Router methods:
 *  GET()          - зөвхөн GET хүсэлт
 *  GET_POST()     - GET болон POST аль алинд нь хариулах үйлдэл
 *  POST_DELETE()  - POST болон DELETE хүсэлтэнд хариулах үйлдэл
 *
 * Эдгээр нь codesaur/router багцын өргөтгөсөн метод бөгөөд
 * RBAC UI-ийн форм submit болон AJAX үйлдлүүдийг илүү уян хатан болгодог.
 *
 * Бүртгэгдсэн маршрутүүд:
 * ───────────────────────────────────────────────────────────────
 * 1) /dashboard/organizations/rbac/alias
 *      → RBAC бүлгүүдийн alias жагсаалт авах
 *
 * 2) /dashboard/organizations/rbac/role/view
 *      → Сонгосон alias бүлгийн role-ууд болон тэдгээрийн permission-г харах
 *
 * 3) /dashboard/organizations/rbac/{alias}/insert/role
 *      → Role шинээр нэмэх (GET: form / POST: insert)
 *
 * 4) /dashboard/organizations/rbac/{alias}/insert/permission
 *      → Permission шинээр нэмэх (GET: form / POST: insert)
 *
 * 5) /dashboard/organizations/rbac/{alias}/role/permission
 *      → Role ↔ Permission холболт (mapping) hiikh
 *      → POST: assign / DELETE: revoke
 *
 * Security:
 * ───────────────────────────────────────────────────────────────
 *  - RBACController дотор RBAC эрх шалгалт хийгдэнэ.
 *  - Энэ Router зөвхөн маршрут map хийх бөгөөд security-ийн шат middleware-д байна.
 *
 * @package Indoraptor RBAC
 */
class RBACRouter extends Router
{
    public function __construct()
    {
        // RBAC alias list
        $this->GET(
            '/dashboard/organizations/rbac/alias',
            [RBACController::class, 'alias']
        )->name('rbac-alias');

        // Role viewer
        $this->GET(
            '/dashboard/organizations/rbac/role/view',
            [RBACController::class, 'viewRole']
        )->name('rbac-role-view');

        // Insert role
        $this->GET_POST(
            '/dashboard/organizations/rbac/{alias}/insert/role',
            [RBACController::class, 'insertRole']
        )->name('rbac-insert-role');

        // Insert permission
        $this->GET_POST(
            '/dashboard/organizations/rbac/{alias}/insert/permission',
            [RBACController::class, 'insertPermission']
        )->name('rbac-insert-permission');

        // Role ↔ Permission modifier
        $this->POST_DELETE(
            '/dashboard/organizations/rbac/{alias}/role/permission',
            [RBACController::class, 'setRolePermission']
        )->name('rbac-set-role-permission');
    }
}
