<?php

namespace Raptor\Template;

use Psr\Log\LogLevel;

use Raptor\Organization\OrganizationModel;
use Raptor\RBAC\Permissions;

class TemplateController extends \Raptor\Controller
{
    use DashboardTrait;
    
    public function userOption()
    {
        $this->twigTemplate(\dirname(__FILE__) . '/user-option-modal.html')->render();
    }
    
    public function manageMenu()
    {
        try {
            if (!$this->isUserCan('system_manage_menu')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new MenuModel($this->pdo);
            $menu = $model->getRows(['ORDER BY' => 'p.position', 'WHERE' => 'p.is_active=1']);            
            $users = $this->retrieveUsersDetail();
            foreach ($menu as &$item) {
                if (isset($users[$item['created_by']])) {
                    $item['created_by'] = $users[$item['created_by']];
                }
                if (isset($users[$item['updated_by']])) {
                    $item['updated_by'] = $users[$item['updated_by']];
                }
            }
            
            $aliases = ['common'];
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $alias_results = $this->query(
                "SELECT alias FROM $org_table WHERE alias!='common' AND is_active=1 GROUP BY alias"
            )->fetchAll();
            foreach ($alias_results as $row) {
               $aliases[] = $row['alias'];
            }
            
            $permissions = [];
            $permissions_table = (new Permissions($this->pdo))->getName();
            $permission_results = $this->query(
                "SELECT CONCAT(alias, '_', name) as permission FROM $permissions_table WHERE is_active=1"
            )->fetchAll();
            foreach ($permission_results as $row) {
               $permissions[] = $row['permission'];
            }
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/manage-menu.html',
                ['menu' => $menu, 'aliases' => $aliases, 'permissions' => $permissions]
            );
            $dashboard->render();            
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'template-menu-manage'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Цэсний жагсаалтыг нээж үзэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Цэсний жагсаалтыг нээж үзэж байна';
                $context += ['aliases' => $aliases, 'permissions' => $permissions, 'menu' => $menu];
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
    
    public function manageMenuInsert()
    {
        try {
            if (!$this->isUserCan('system_manage_menu')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $payload = [];
            $content = [];
            $parsedBody = $this->getParsedBody();
            foreach ($parsedBody as $index => $value) {
                if (\is_array($value)) {
                    foreach ($value as $key => $value) {
                        $content[$key][$index] = $value;
                    }
                } else {
                    $payload[$index] = $value;
                }
            }
            if (empty($payload) || empty($content)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            
            $model = new MenuModel($this->pdo);
            $payload['is_visible'] = ($payload['is_visible'] ?? 'off' ) == 'on' ? 1 : 0;
            $record = $model->insert($payload + ['created_by' => $this->getUserId()], $content);
            if (empty($record)) {
                throw new \Exception($this->text('record-insert-error'));
            }            
            $this->respondJSON([
                'status' => 'success',
                'message' => $this->text('record-insert-success')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            $context = ['action' => 'template-menu-create'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Цэс үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = 'Цэс үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['record' => $record];
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
    
    public function manageMenuUpdate()
    {
        try {
            if (!$this->isUserCan('system_manage_menu')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $parsedBody = $this->getParsedBody();
            if (empty($parsedBody)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            if (!isset($parsedBody['id'])
                || !\filter_var($parsedBody['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($parsedBody['id'], \FILTER_VALIDATE_INT);
            unset($parsedBody['id']);
            $parsedBody['is_visible'] = ($parsedBody['is_visible'] ?? 'off' ) == 'on' ? 1 : 0;
            
            $model = new MenuModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $payload = [];
            $content = [];
            $updates = [];
            foreach ($parsedBody as $index => $value) {
                if (\is_array($value)) {
                    foreach ($value as $key => $value) {
                        $content[$key][$index] = $value;
                        if ($record['localized'][$index][$key] != $value) {
                            $updates[] = "{$index}_{$key}";
                        }
                    }
                } else {
                    $payload[$index] = $value;
                    if ($record[$index] != $value) {
                        $updates[] = $index;
                    }
                }
            }
            if (empty($updates)) {
                throw new \InvalidArgumentException('No update!');
            }
            
            $updated = $model->updateById(
                $id, $payload + ['updated_by' => $this->getUserId()], $content
            );
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $this->respondJSON([
                'type' => 'primary',
                'status' => 'success',
                'message' => $this->text('record-update-success')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            $context = ['action' => 'template-menu-update'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Цэсний мэдээллийг шинэчлэх гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай цэсний мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
    
    public function manageMenuDelete()
    {
        try {
            if (!$this->isUserCan('system_manage_menu')) {
                throw new \Exception('No permission for an action [deactivate]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);

            $model = new MenuModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }            
            $deactivated = $model->deactivateById($id, [
                'updated_by' => $this->getUserId(), 'updated_at' => \date('Y-m-d H:i:s')
            ]);
            if (!$deactivated) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);            
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
        } finally {
            $context = ['action' => 'template-menu-delete'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Цэс устгах/идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '[{server_request.body.caption}] цэсийг [{server_request.body.reason}] шалтгаанаар устгаж/идэвхгүй болголоо';
                $context += ['record' => $record];
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
}
