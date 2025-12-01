<?php

namespace Raptor\Organization;

use Psr\Log\LogLevel;

use Raptor\Content\FileController;

class OrganizationController extends FileController
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        if (!$this->isUserCan('system_organization_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $dashboard = $this->twigDashboard(__DIR__ . '/organization-index.html');
        $dashboard->set('title', $this->text('organizations'));
        $dashboard->render();
        
        $this->indolog('organizations', LogLevel::NOTICE, 'Байгууллагуудын жагсаалтыг үзэж байна', ['action' => 'index']);
    }
    
    public function list()
    {
        try {
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $table = (new OrganizationModel($this->pdo))->getName();
            $this->respondJSON([
                'status' => 'success',
                'list' => $this->query("SELECT id,name,alias,logo,logo_size FROM $table WHERE is_active=1 ORDER BY id")->fetchAll()
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }
    
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_organization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new OrganizationModel($this->pdo);
            if ($this->getRequest()->getMethod() == 'POST') {
                $payload = $this->getParsedBody();
                if (empty($payload['alias'])
                    || empty($payload['name'])
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $record = $model->insert($payload + ['created_by' => $this->getUserId()]);
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $id = $record['id'];
                $this->setFolder("/{$model->getName()}/$id");
                $this->allowImageOnly();
                $logo = $this->moveUploaded('logo');
                if (!empty($logo['path'])) {
                    $model->updateById($id, [
                        'logo'      => $logo['path'],
                        'logo_file' => $logo['file'],
                        'logo_size' => $logo['size']
                    ]);
                }
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                $this->twigTemplate(
                    __DIR__ . '/organization-insert-modal.html',
                    ['parents' => $model->fetchAllPotentialParents()]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'create'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Байгууллага үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = 'Байгууллага [{record.name}] амжилттай үүслээ';
                $context += ['id' => $id, 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Байгууллага үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->indolog('organizations', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        try {
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new OrganizationModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $record['rbac_users'] = $this->retrieveUsersDetail(
                $record['created_by'], $record['updated_by']
            );
            if (!empty($record['parent_id'])) {
                $parent = $model->getRowWhere([
                    'id' => $record['parent_id'],
                    'is_active' => 1
                ]);
                if (empty($parent)) {
                    $parent['name'] = "- no parent or it's parent deleted -";
                }
                $record['parent_name'] = $parent['name'];
            }
            $this->twigTemplate(
                __DIR__ . '/organization-retrieve-modal.html',
                ['record' => $record]
            )->render();
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'view', 'id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай байгууллагын мэдээллийг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.name}] байгууллагын мэдээллийг үзэж байна';
                $context += ['record' => $record];
            }
            $this->indolog('organizations', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            if (!$this->isUserCan('system_organization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new OrganizationModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            if ($this->getRequest()->getMethod() == 'PUT') {
                $payload = $this->getParsedBody();
                if (empty($payload['alias'])
                    || empty($payload['name'])
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload['alias'] = \preg_replace('/[^A-Za-z0-9_-]/', '', $payload['alias']);
                
                if ($payload['logo_removed'] == 1) {
                    if (\file_exists($record['logo_file'])) {
                        \unlink($record['logo_file']);
                        $record['logo_file'] = '';
                    }
                    $payload['logo'] = '';
                    $payload['logo_file'] = '';
                    $payload['logo_size'] = 0;
                }
                unset($payload['logo_removed']);
                
                $this->setFolder("/{$model->getName()}/$id");
                $this->allowImageOnly();
                $logo = $this->moveUploaded('logo');
                if ($logo) {
                    if (!empty($record['logo_file'])
                        && \file_exists($record['logo_file'])
                    ) {
                        \unlink($record['logo_file']);
                    }
                    $payload['logo'] = $logo['path'];
                    $payload['logo_file'] = $logo['file'];
                    $payload['logo_size'] = $logo['size'];
                }
                
                $updates = [];
                foreach ($payload as $field => $value) {
                    if ($record[$field] != $value) {
                        $updates[] = $field;
                    }
                }
                if (empty($updates)) {
                    throw new \InvalidArgumentException('No update!');
                }
                $payload['updated_at'] = \date('Y-m-d H:i:s');
                $payload['updated_by'] = $this->getUserId();
                $updated = $model->updateById($id, $payload);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                $this->twigTemplate(
                    __DIR__ . '/organization-update-modal.html',
                    [
                        'record' => $record,
                        'parents' => $model->fetchAllPotentialParents()
                    ]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'update', 'id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай байгууллагын мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.name}] байгууллагын мэдээллийг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.name}] байгууллагын мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record];
            }
            $this->indolog('organizations', $level, $message, $context);
        }
    }
    
    public function deactivate()
    {
        try {
            if (!$this->isUserCan('system_organization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            if ($this->getUser()->organization['id'] == $id) {
                throw new \Exception('Cannot remove currently active organization!', 403);
            } elseif ($id == 1) {
                throw new \Exception('Cannot remove first organization!', 403);
            }
            
            $model = new OrganizationModel($this->pdo);
            $deactivated = $model->deactivateById(
                $id,
                [
                    'updated_by' => $this->getUserId(),
                    'updated_at' => \date('Y-m-d H:i:s')
                ]
            );
            if (!$deactivated) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Байгууллагыг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{server_request.body.id} дугаартай [{server_request.body.name}] байгууллагыг [{server_request.body.reason}] шалтгаанаар идэвхгүй болголоо';
            }
            $this->indolog('organizations', $level, $message, $context);
        }
    }
}
