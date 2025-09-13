<?php

namespace Raptor\RBAC;

use Psr\Log\LogLevel;

class RBACController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
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
            
            $roles_table = (new Roles($this->pdo))->getName();
            $select_roles = $this->prepare("SELECT id,name,description FROM $roles_table WHERE alias=:alias AND is_active=1 AND not (name='coder' AND alias='system')");
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
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'rbac-alias'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'RBAC жагсаалтыг нээж үзэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC [{alias}] жагсаалтыг нээж үзэж байна';
                $context += ['alias' => $alias];
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
    
    public function insertRole(string $alias)
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $payload = $this->getParsedBody();
            $title = $this->getQueryParams()['title'] ?? '';
            if ($this->getRequest()->getMethod() == 'POST') {
                $record = (new Roles($this->pdo))->insert(
                    $payload + ['alias' => $alias, 'created_by' => $this->getUserId()]
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                $this->twigTemplate(
                    \dirname(__FILE__) . '/rbac-insert-role-modal.html',
                    ['alias' => $alias, 'title' => $title]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON([
                    'status' => 'error',
                    'title' => $this->text('error'),
                    'message' => $err->getMessage()
                ], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = [
                'action' => 'rbac-create-role',
                'alias' => $alias
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'RBAC дүр үүсгэх үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif (!empty($record)) {
                $level = LogLevel::INFO;
                $message = 'RBAC дүр [{record.name}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['id' => $record['id'], 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC дүр үүсгэх үйлдлийг амжилттай эхлүүллээ';
            }
            $this->indolog('dashboard', $level, $message, $context);
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
                "SELECT * FROM $roles_table " .
                "WHERE CONCAT_WS('_',alias,name)=:role AND is_active=1 " .
                "ORDER BY id desc LIMIT 1"
            );
            $select_role->bindParam(':role', $values['role']);
            if ($select_role->execute()) {
                $record = $select_role->fetch();
            }
            if (empty($record)) {
                throw new \Exception('Record not found', 404);
            }
            $values += $record;
            $this->twigTemplate(\dirname(__FILE__) . '/rbac-view-role-modal.html', $values)->render();
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'rbac-view-role'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'RBAC дүр мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC дүр [{record.name}] мэдээллийг нээж үзэж байна';
                $context += ['alias' => $record['alias'], 'id' => $record['id'], 'record' => $record];
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
    
    public function insertPermission(string $alias)
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $payload = $this->getParsedBody();
            $title = $this->getQueryParams()['title'] ?? '';
            if ($this->getRequest()->getMethod() == 'POST') {
                $record = (new Permissions($this->pdo))->insert(
                    $payload + ['alias' => $alias, 'created_by' => $this->getUserId()]
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
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
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON([
                    'status' => 'error',
                    'title' => $this->text('error'),
                    'message' => $err->getMessage()
                ], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = [
                'action' => 'rbac-create-permission',
                'alias' => $alias
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'RBAC зөвшөөрөл үүсгэх үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif (!empty($record)) {
                $level = LogLevel::INFO;
                $message = 'RBAC зөвшөөрөл [{record.name}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['id' => $record['id'], 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC зөвшөөрөл үүсгэх үйлдлийг амжилттай эхлүүллээ';
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
    
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
                'alias' => $alias,
                'role_id' => $parsedBody['role_id'],
                'permission_id' => $parsedBody['permission_id']
            ];
            
            $model = new RolePermission($this->pdo);
            $row = $model->getRowBy($payload);
            $method = $this->getRequest()->getMethod();
            if ($method == 'POST') {
                if (empty($row) && $model->insert($payload)) {
                    $message = 'Role -> Permission заагдлаа';
                    return $this->respondJSON([
                        'type' => 'success',
                        'message' => $this->text('record-insert-success')
                    ]);
                }
            } elseif ($method == 'DELETE') {
                if (isset($row['id']) && $model->deleteById($row['id'])) {
                    $message = 'Role -> Permission устгагдлаа';
                    return $this->respondJSON([
                        'type' => 'primary',
                        'message' => $this->text('record-successfully-deleted')
                    ]);
                }
            }
            throw new \Exception($this->text('invalid-values'), 400);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'type' => 'error',
                'title' => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        }  finally {
            $context = [
                'action' => 'rbac-set-role-permission',
                'alias' => $alias
            ];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Role -> Permission тохируулах үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $context += $payload;
                $message = 'Role -> Permission ' . ($method == 'DELETE' ? 'устгагдлаа' : 'заагдлаа');
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
}
