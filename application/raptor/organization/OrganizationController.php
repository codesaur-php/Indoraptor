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
        
        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/organization-index.html');
        $dashboard->set('title', $this->text('organizations'));
        $dashboard->render();
        
        $this->indolog('organizations', LogLevel::NOTICE, 'Байгууллагуудын жагсаалтыг нээж байна', ['action' => 'index']);
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
                'list' => $this->query("SELECT id,name,alias,logo FROM $table WHERE is_active=1")->fetchAll()
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
                    $record = $model->updateById($id, ['logo' => $logo['path']]);
                }                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                $this->twigTemplate(
                    \dirname(__FILE__) . '/organization-insert-modal.html',
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
            } elseif (!empty($record)) {
                $level = LogLevel::INFO;
                $message = 'Байгууллага [{record.name}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
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
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $record['rbac_users'] = $this->retrieveUsersDetail(
                $record['created_by'], $record['updated_by']
            );
            if (!empty($record['parent_id'])) {
                $parent = $model->getById($record['parent_id']);
                if (empty($parent)) {
                    $parent['name'] = "- no parent or it's parent deleted -";
                }
                $record['parent_name'] = $parent['name'];
            }
            $this->twigTemplate(
                \dirname(__FILE__) . '/organization-retrieve-modal.html',
                ['record' => $record]
            )->render();
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'view', 'id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай байгууллагын мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.name}] байгууллагын мэдээллийг нээж үзэж байна';
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
            $record = $model->getById($id);
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
                $this->setFolder("/{$model->getName()}/$id");
                $this->allowImageOnly();
                $logo = $this->moveUploaded('logo');
                if ($logo) {
                    $payload['logo'] = $logo['path'];
                }
                $current_logo_name = empty($record['logo']) ? '' : \basename($record['logo']);
                if (!empty($current_logo_name)) {
                    if ($this->getLastUploadError() == -1) {
                        $this->unlinkByName($current_logo_name);
                        $payload['logo'] = '';
                    } elseif (isset($payload['logo'])
                        && \basename($payload['logo']) != $current_logo_name
                    ) {
                        $this->unlinkByName($current_logo_name);
                    }
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
                    \dirname(__FILE__) . '/organization-update-modal.html',
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
            } elseif (!empty($updated)) {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.name}] байгууллагын мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.name}] байгууллагын мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record];
            }
            $this->indolog('organizations', $level, $message, $context);
        }
    }
    
    public function delete()
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
                $message = '{record.name} байгууллагыг [{server_request.body.reason}] шалтгаанаар идэвхгүй болголоо';
                $context += ['record' => $record];
            }
            $this->indolog('organizations', $level, $message, $context);
        }
    }
}
