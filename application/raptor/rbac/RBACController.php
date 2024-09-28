<?php

namespace Raptor\RBAC;

use Psr\Log\LogLevel;

class RBACController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function alias()
    {
        try {
            $queryParams = $this->getQueryParams();
            $alias = $queryParams['alias'] ?? null;
            $title = $queryParams['title'] ?? null;
            $context = ['alias' => $alias];
            
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $roles_table = (new Roles($this->pdo))->getName();
            $select_roles = $this->prepare("SELECT id,name,description FROM $roles_table WHERE alias=:alias AND is_active=1 AND !(name='coder' AND alias='system')");
            $select_roles->bindParam(':alias', $alias);
            if ($select_roles->execute()) {
                $roles = $select_roles->fetchAll();
            } else {
                $roles = [];
            }
            
            $permissions_table = (new Permissions($this->pdo))->getName();
            $select_perms = $this->prepare("SELECT id,name,description FROM $permissions_table WHERE alias=:alias AND is_active=1 ORDER BY module");
            $select_perms->bindParam(':alias', $alias);
            if ($select_perms->execute()) {
                $permissions = $select_perms->fetchAll();
            } else {
                $permissions = [];
            }
            
            $role_perm_table = (new RolePermission($this->pdo))->getName();
            $select_rp = $this->prepare("SELECT role_id,permission_id FROM $role_perm_table WHERE alias=:alias AND is_active=1");
            $select_rp->bindParam(':alias', $alias);
            $role_permission = [];
            if ($select_rp->execute()) {
                foreach ($select_rp->fetchAll() as $row) {
                    $role_permission[$row['role_id']][$row['permission_id']] = true;
                }
            }
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/rbac-alias.html',
                [
                    'alias' => $alias, 
                    'title' => $title, 
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'role_permission' => $role_permission
                ]
            );
            $dashboard->set('title', "RBAC - $alias");
            $dashboard->render();
            
            $level = LogLevel::NOTICE;
            $message = "RBAC [$alias] жагсаалтыг нээж үзэж байна";
        } catch (\Throwable $e) {
            $message = 'RBAC жагсаалтыг нээх үед алдаа гарлаа. ' . $e->getMessage();
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
        } finally {
            $this->indolog('rbac', $level, $message, $context);
        }
    }
    
    public function insertRole(string $alias)
    {
        try {
            $title = $this->getQueryParams()['title'] ?? '';
            $context = ['reason' => 'rbac-insert-role'];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            $context['payload'] = $payload = $this->getParsedBody();
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            if ($is_submit) {
                $id = (new Roles($this->pdo))->insert($payload + ['alias' => $alias]);
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $context['id'] = $id;
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $this->indolog('rbac', LogLevel::INFO, 'RBAC дүр шинээр нэмэх үйлдлийг амжилттай гүйцэтгэлээ', $context);
            } else {
                $this->twigTemplate(\dirname(__FILE__) . '/rbac-insert-role-modal.html', ['alias' => $alias, 'title' => $title])->render();
                
                $this->indolog('rbac', LogLevel::NOTICE, 'RBAC дүр шинээр нэмэх үйлдлийг амжилттай эхлүүллээ', $context);
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON([
                    'status' => 'error',
                    'title' => $this->text('error'),
                    'message' => $e->getMessage()
                ], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $this->indolog('rbac', LogLevel::ERROR, 'RBAC дүр шинээр нэмэх үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо', $context);
        }
    }
    
    public function viewRole()
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $values = ['role' => $this->getQueryParams()['role'] ?? ''];
            
            $roles_table = (new Roles($this->pdo))->getName();
            $select_role = $this->prepare(
                "SELECT id,description FROM $roles_table " .
                "WHERE CONCAT_WS('_',alias,name)=:role AND is_active=1 " .
                "ORDER BY id desc LIMIT 1"
            );
            $select_role->bindParam(':role', $values['role']);
            if ($select_role->execute()) {
                $role = $select_role->fetch();
            }
            if (empty($role)) {
                throw new \Exception('Record not found', 404);
            }
            $values += $role;
            $this->twigTemplate(\dirname(__FILE__) . '/rbac-view-role-modal.html', $values)->render();
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
        }
    }
    
    public function insertPermission(string $alias)
    {
        try {
            $title = $this->getQueryParams()['title'] ?? '';
            $context = ['reason' => 'rbac-insert-permission'];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            $context['payload'] = $payload = $this->getParsedBody();
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            if ($is_submit) {
                $id = (new Permissions($this->pdo))->insert($payload + ['alias' => $alias]);
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $context['id'] = $id;
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $this->indolog('rbac', LogLevel::INFO, 'RBAC зөвшөөрөл шинээр нэмэх үйлдлийг амжилттай гүйцэтгэлээ', $context);
            } else {
                $permissions_table = (new Permissions($this->pdo))->getName();
                $select_modules = $this->prepare(
                    "SELECT DISTINCT(module) FROM $permissions_table " .
                    "WHERE alias=:alias AND is_active=1 AND module<>''"
                );
                $select_modules->bindParam(':alias', $alias);
                if ($select_modules->execute()) {
                    $modules = $select_modules->fetchAll();
                } else {
                    $modules = [];
                }
                $this->twigTemplate(
                    \dirname(__FILE__) . '/rbac-insert-permission-modal.html',
                    ['alias' => $alias, 'title' => $title, 'modules' => $modules]
                )->render();
                
                $this->indolog('rbac', LogLevel::NOTICE, 'RBAC зөвшөөрөл шинээр нэмэх үйлдлийг амжилттай эхлүүллээ', $context);
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON([
                    'status' => 'error',
                    'title' => $this->text('error'),
                    'message' => $e->getMessage()
                ], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $this->indolog('rbac', LogLevel::ERROR, 'RBAC зөвшөөрөл шинээр нэмэх үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо', $context);
        }
    }
    
    public function setRolePermission(string $alias)
    {
        try {
            $context = ['reason' => 'set-role-permission'];
                
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($alias)
                || empty($payload['role_id'])
                || empty($payload['permission_id'])
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $record = [
                'alias' => $alias,
                'role_id' => $payload['role_id'],
                'permission_id' => $payload['permission_id'],
                'is_active' => 1
            ];
            
            $level = LogLevel::NOTICE;
            $context['record'] = $record;
            
            $model = new RolePermission($this->pdo);
            $row = $model->getRowBy($record);
            $method = $this->getRequest()->getMethod();
            if ($method == 'POST') {
                if (empty($row) && $model->insert($record)) {
                    $message = 'Role -> Permission заагдлаа.';
                    return $this->respondJSON([
                        'type' => 'success',
                        'message' => $this->text('record-insert-success')
                    ]);
                }
            } elseif ($method == 'DELETE') {
                if (isset($row['id']) && $model->deleteById($row['id'])) {
                    $message = 'Role -> Permission устгагдлаа.';
                    return $this->respondJSON([
                        'type' => 'primary',
                        'message' => $this->text('record-successfully-deleted')
                    ]);
                }
            }
            throw new \Exception($this->text('invalid-values'), 400);
        } catch (\Throwable $e) {
            $this->respondJSON([
                'type' => 'error',
                'title' => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Role -> Permission тохируулах үеп алдаа гарлаа.';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('rbac', $level, $message, $context);
        }
    }
}
