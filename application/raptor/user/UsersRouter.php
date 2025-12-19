<?php

namespace Raptor\User;

use codesaur\Router\Router;

/**
 * Хэрэглэгчийн модулийн маршрут (route)-уудыг бүртгэгч router класс.
 *
 * Энэ router нь UsersController-ийн бүх CRUD болон RBAC холбоотой үйлдлүүдийг
 * dashboard хэсэгт зориулан HTTP замтай холбож өгөх үүрэгтэй.
 *
 *  ✔ Хэрэглэгчийн жагсаалт (index, list)
 *  ✔ Шинэ хэрэглэгч үүсгэх / мэдээлэл засварлах / харах
 *  ✔ Идэвхгүй болгох
 *  ✔ Байгууллага холбох
 *  ✔ RBAC дүр холбох
 *  ✔ Нууц үг солих
 *  ✔ Forgot/Signup хүсэлтүүдийн modal жагсаалт
 *  ✔ Signup хүсэлт approve/deactivate
 *
 *  Маршрут бүр нь:
 *      - URL хаяг
 *      - HTTP method (GET, POST, PUT, DELETE)
 *      - Ямар Controller::method руу очих
 *      - name() → system дотор замыг нэрээр дуудаж ашиглах
 *
 * @package Raptor\User
 */
class UsersRouter extends Router
{
    public function __construct()
    {
        /**
         * ----------------------------------------------------------
         * DASHBOARD - Users main list
         * ----------------------------------------------------------
         */

        // Хэрэглэгчийн dashboard жагсаалт харуулах
        $this->GET('/dashboard/users', [UsersController::class, 'index'])->name('users');

        // Хэрэглэгчдийн жагсаалтыг AJAX-аар авах
        $this->GET('/dashboard/users/list', [UsersController::class, 'list'])->name('users-list');

        /**
         * ----------------------------------------------------------
         * CREATE / UPDATE / VIEW USER
         * ----------------------------------------------------------
         */
        // Шинэ хэрэглэгч үүсгэх (form үзүүлэх + submit)
        $this->GET_POST('/dashboard/users/insert', [UsersController::class, 'insert'])->name('user-insert');
        
        // Хэрэглэгчийн мэдээлэл засварлах (form үзүүлэх + update хийх PUT)
        $this->GET_PUT('/dashboard/users/update/{uint:id}', [UsersController::class, 'update'])->name('user-update');
        
        // Хэрэглэгчийн дэлгэрэнгүй мэдээлэл харах
        $this->GET('/dashboard/users/view/{uint:id}', [UsersController::class, 'view'])->name('user-view');

        /**
         * ----------------------------------------------------------
         * DELETE / DEACTIVATE
         * ----------------------------------------------------------
         */
        // Хэрэглэгчийг идэвхгүй болгох
        $this->DELETE('/dashboard/users/deactivate', [UsersController::class, 'deactivate'])->name('user-deactivate');

        /**
         * ----------------------------------------------------------
         * ORGANIZATION SET
         * ----------------------------------------------------------
         */

        // Хэрэглэгчийг байгууллага дээр холбох / устгах
        $this->GET_POST('/dashboard/users/set/organization/{uint:id}', [UsersController::class, 'setOrganization'])->name('user-set-organization');

        /**
         * ----------------------------------------------------------
         * ROLE (RBAC) SET
         * ----------------------------------------------------------
         */
        // Хэрэглэгчийн RBAC дүр тохируулах
        $this->GET_POST('/dashboard/users/set/role/{uint:id}', [UsersController::class, 'setRole'])->name('user-set-role');

        /**
         * ----------------------------------------------------------
         * PASSWORD RESET
         * ----------------------------------------------------------
         */
        // Хэрэглэгчийн нууц үг солих (өөрийнхөө эсвэл system_coder → бусдын)
        $this->GET_POST('/dashboard/users/set/password/{uint:id}', [UsersController::class, 'setPassword'])->name('user-set-password');

        /**
         * ----------------------------------------------------------
         * SIGNUP / FORGOT REQUESTS MODALS
         * ----------------------------------------------------------
         */
        // Signup / Forgot хүсэлтийн modal жагсаалт (зөвхөн view)
        $this->GET('/dashboard/users/requests/{table}/modal', [UsersController::class, 'requestsModal'])->name('user-requests-modal');

        /**
         * ----------------------------------------------------------
         * SIGNUP APPROVAL / DEACTIVATION
         * ----------------------------------------------------------
         */
        // Signup хүсэлтийг батлах → хэрэглэгч болгож insert хийх
        $this->POST('/dashboard/users/signup/approve', [UsersController::class, 'signupApprove'])->name('user-signup-approve');

        // Signup хүсэлтийг идэвхгүй болгох
        $this->DELETE('/dashboard/users/signup/deactivate', [UsersController::class, 'signupDeactivate'])->name('user-signup-deactivate');
    }
}
