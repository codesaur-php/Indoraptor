<?php

namespace Raptor\RBAC;

use Psr\Log\LogLevel;

/**
 * RBACController - RBAC (Role-Based Access Control) модулийн
 * UI болон API-д зориулсан үндсэн контроллер.
 *
 * Үндсэн үүрэг:
 * ───────────────────────────────────────────────────────────────
 *  - RBAC alias бүрийн роль, зөвшөөрлийн (permission) жагсаалт харуулах
 *  - Role үүсгэх (GET: form, POST: insert)
 *  - Permission үүсгэх (GET: form, POST: insert)
 *  - Role ↔ Permission холболт хийх, устгах
 *  - Тухайн рольд хамаарах дэлгэрэнгүйг харах (modal)
 *
 * Security:
 * ───────────────────────────────────────────────────────────────
 *  - Бүх RBAC удирдлагын үйлдэлд "system_rbac" permission заавал шалгана.
 *  - Permission-гүй хэрэглэгчдэд DashboardTrait ашиглан
 *    "no permission" alert/modal үзүүлнэ.
 *
 * UI Rendering Pipeline:
 * ───────────────────────────────────────────────────────────────
 *  - DashboardTrait::twigDashboard() ашиглан sidebar + content бүхий layout
 *  - twigTemplate() ашиглан modal/forms рендерлэх
 *
 * Logging (audit trail):
 * ───────────────────────────────────────────────────────────────
 *  - indolog() ашиглан dashboard_log хүснэгтэд бүх үйлдлийг бүртгэнэ.
 *  - Амжилттай/алдаатай аль ч тохиолдолд finally дотор лог бичигдэнэ.
 *
 * RBACController нь:
 *    → RBACRouter-оор дамжин бүх RBAC админ интерфэйс рүү ханддаг
 *    → Roles, Permissions, RolePermission модельтэй шууд ажиллана
 *    → RBAC UI-г бүрдүүлдэг хамгийн гол контроллер юм.
 */
class RBACController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * RBAC alias жагсаалт (role + permission matrix) үзүүлэх.
     *
     * URL жишээ:
     *   /dashboard/organizations/rbac/alias?alias=user&title=User+RBAC
     *
     * Workflow:
     * ───────────────────────────────────────────────────────────────
     * 1) "system_rbac" permission шалгах
     * 2) Query параметрээс alias болон title авах
     * 3) Roles (coder-role-оос бусад)-ийг alias-аар шүүх
     * 4) Permissions-ийг alias-аар шүүх
     * 5) RolePermission mapping-ийг бүдүүвч байдлаар авах
     * 6) Dashboard layout руу rbac-alias.html template дамжуулах
     * 7) Алдаа гарвал dashboardProhibited() руу шилжих
     *
     * @return void
     */
    public function alias()
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $queryParams = $this->getQueryParams();
            $alias = $queryParams['alias'] ?? null;
            if (empty($alias)) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $title = $queryParams['title'] ?? null;

            // Roles жагсаалт
            $roles_table = (new Roles($this->pdo))->getName();
            $select_roles = $this->prepare(
                "SELECT id,name,description FROM $roles_table
                 WHERE alias=:alias AND NOT (name='coder' AND alias='system')"
            );
            $select_roles->bindParam(':alias', $alias);
            $select_roles->execute();
            $roles = $select_roles->fetchAll() ?: [];

            // Permissions жагсаалт
            $permissions_table = (new Permissions($this->pdo))->getName();
            $select_perms = $this->prepare(
                "SELECT id,name,description FROM $permissions_table
                 WHERE alias=:alias ORDER BY module"
            );
            $select_perms->bindParam(':alias', $alias);
            $select_perms->execute();
            $permissions = $select_perms->fetchAll() ?: [];

            // RolePermission mapping
            $role_perm_table = (new RolePermission($this->pdo))->getName();
            $select_rp = $this->prepare(
                "SELECT role_id,permission_id FROM $role_perm_table WHERE alias=:alias"
            );
            $select_rp->bindParam(':alias', $alias);
            $role_permission = [];
            if ($select_rp->execute()) {
                foreach ($select_rp->fetchAll() as $row) {
                    $role_permission[$row['role_id']][$row['permission_id']] = true;
                }
            }

            // Dashboard руу рендерлэх
            $dashboard = $this->twigDashboard(
                __DIR__ . '/rbac-alias.html',
                [
                    'alias'           => $alias,
                    'title'           => $title,
                    'roles'           => $roles,
                    'permissions'     => $permissions,
                    'role_permission' => $role_permission,
                ]
            );
            $dashboard->set('title', "RBAC - $alias");
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            // Audit log
            $context = ['action' => 'rbac-alias'];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'RBAC жагсаалтыг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC [{alias}] жагсаалтыг үзэж байна';
                $context += ['alias' => $alias];
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }

    /**
     * Role үүсгэх (GET: form, POST: insert).
     *
     * URL:
     *   GET  /dashboard/organizations/rbac/{alias}/insert/role
     *   POST /dashboard/organizations/rbac/{alias}/insert/role
     *
     * POST Workflow:
     * ───────────────────────────────────────────────────────────────
     * 1) Permission шалгах (system_rbac)
     * 2) Request payload баталгаажуулах
     * 3) New Role insert (alias + created_by)
     * 4) JSON success response буцаах
     *
     * GET Workflow:
     * ───────────────────────────────────────────────────────────────
     * 1) Modal form (rbac-insert-role-modal.html) рендерлэх
     *
     * @param string $alias RBAC бүлгийн alias (system, user, content ...)
     * @return void
     */
    public function insertRole(string $alias)
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $payload = $this->getParsedBody();
            $title   = $this->getQueryParams()['title'] ?? '';
            if ($this->getRequest()->getMethod() == 'POST') {
                // Role insert
                $record = (new Roles($this->pdo))->insert(
                    $payload + ['alias' => $alias, 'created_by' => $this->getUserId()]
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $this->respondJSON([
                    'status'  => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                // Modal form render
                $this->twigTemplate(
                    __DIR__ . '/rbac-insert-role-modal.html',
                    ['alias' => $alias, 'title' => $title]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $err->getMessage(),
                ], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // Audit log
            $context = ['action' => 'rbac-create-role', 'alias' => $alias];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'RBAC дүр үүсгэх явцад алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = 'RBAC дүр [{record.name}] амжилттай үүсгэлээ';
                $context += ['id' => $record['id'], 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC дүр үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }

    /**
     * Дүрийн дэлгэрэнгүйг харах (modal).
     *
     * URL:
     *   /dashboard/organizations/rbac/role/view?role=system_coder
     *
     * @return void
     */
    public function viewRole()
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $roleKey = $this->getQueryParams()['role'] ?? '';
            $values  = ['role' => $roleKey];

            // Role-г alias_name байдлаар lookup хийх
            $roles_table = (new Roles($this->pdo))->getName();
            // String concatenation - SQLite болон PostgreSQL дээр ||, MySQL дээр CONCAT_WS
            // SQLite дээр CONCAT_WS байхгүй тул || эсвэл CONCAT ашиглана
            $concat_expr = ($this->getDriverName() == 'pgsql' || $this->getDriverName() == 'sqlite')
                ? "alias || '_' || name"
                : "CONCAT_WS('_',alias,name)";
            $select_role = $this->prepare(
                "SELECT * FROM $roles_table
                 WHERE $concat_expr=:role
                 ORDER BY id DESC LIMIT 1"
            );
            $select_role->bindParam(':role', $roleKey);
            $select_role->execute();
            $record = $select_role->fetch();
            if (empty($record)) {
                throw new \Exception('Record not found', 404);
            }
            $values += $record;

            $this->twigTemplate(
                __DIR__ . '/rbac-view-role-modal.html',
                $values
            )->render();

        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'rbac-view-role'];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'RBAC дүрийн мэдээлэл нээх үед алдаа гарлаа';
                $context += ['error' => ['code'=>$err->getCode(), 'message'=>$err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC дүр [{record.name}] дэлгэрэнгүйг үзэж байна';
                $context += [
                    'alias'  => $record['alias'],
                    'id'     => $record['id'],
                    'record' => $record
                ];
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }

    /**
     * Permission үүсгэх (GET: form, POST: insert).
     *
     * GET:
     *   - Зөвхөн modal form рендерлэх
     *   - Тухайн alias-ийн permission module-уудын жагсаалт авч өгөх
     *
     * POST:
     *   - Permission-г insert хийнэ
     *   - created_by = current user
     *   - JSON амжилттай мессеж буцаах
     *
     * @param string $alias
     * @return void
     */
    public function insertPermission(string $alias)
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $payload = $this->getParsedBody();
            $title   = $this->getQueryParams()['title'] ?? '';
            if ($this->getRequest()->getMethod() == 'POST') {
                $record = (new Permissions($this->pdo))->insert(
                    $payload + ['alias' => $alias, 'created_by' => $this->getUserId()]
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $this->respondJSON([
                    'status'  => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                // Module-уудын жагсаалт (UI dropdown)
                $permissions_table = (new Permissions($this->pdo))->getName();
                $select_modules = $this->prepare(
                    "SELECT DISTINCT(module) FROM $permissions_table
                     WHERE alias=:alias AND module<>''"
                );
                $select_modules->bindParam(':alias', $alias);
                $select_modules->execute();
                $modules = $select_modules->fetchAll() ?: [];

                $this->twigTemplate(
                    __DIR__ . '/rbac-insert-permission-modal.html',
                    ['alias' => $alias, 'title' => $title, 'modules' => $modules]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $err->getMessage()
                ], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // Audit log
            $context = ['action' => 'rbac-create-permission', 'alias' => $alias];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'RBAC зөвшөөрөл үүсгэх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message'=>$err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = 'RBAC зөвшөөрөл [{record.name}] амжилттай үүсгэлээ';
                $context += ['id'=>$record['id'], 'record'=>$record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC зөвшөөрөл үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }

    /**
     * Роль → Зөвшөөрөл (permission) холболт хийх/устгах.
     *
     * POST   → холбох  
     * DELETE → салгах
     *
     * Payload шаардлага:
     *   - alias
     *   - role_id
     *   - permission_id
     *
     * @param string $alias RBAC бүлгийн alias
     * @return void
     */
    public function setRolePermission(string $alias)
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $parsedBody = $this->getParsedBody();
            if (empty($alias)
                || empty($parsedBody['role_id'])
                || empty($parsedBody['permission_id'])
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }

            $payload = [
                'alias'         => $alias,
                'role_id'       => $parsedBody['role_id'],
                'permission_id' => $parsedBody['permission_id']
            ];
            $model = new RolePermission($this->pdo);
            $row   = $model->getRowWhere($payload);
            $method = $this->getRequest()->getMethod();
            if ($method === 'POST') {
                // Assign
                if (empty($row) && $model->insert($payload)) {
                    return $this->respondJSON([
                        'type'    => 'success',
                        'message' => $this->text('record-insert-success')
                    ]);
                }
            } elseif ($method === 'DELETE') {
                // Revoke
                if (isset($row['id']) && $model->deleteById($row['id'])) {
                    return $this->respondJSON([
                        'type'    => 'primary',
                        'message' => $this->text('record-successfully-deleted')
                    ]);
                }
            }
            
            throw new \Exception($this->text('invalid-values'), 400);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'type'    => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            // Audit log
            $context = ['action' => 'rbac-set-role-permission', 'alias' => $alias];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'Role → Permission тохируулах үед алдаа гарлаа';
                $context += ['error' => ['code'=>$err->getCode(),'message'=>$err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $context += $payload;
                $message = 'Role → Permission ' . ($method === 'DELETE'
                    ? 'устгагдлаа'
                    : 'заагдлаа');
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
}
