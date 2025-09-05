<?php

namespace Raptor\Organization;

use Psr\Log\LogLevel;

use Raptor\File\FileController;

class OrganizationController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        try {
            $context = ['model' => OrganizationModel::class];
            
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/organization-index.html');
            $dashboard->set('title', $this->text('organizations'));
            $dashboard->render();
            
            $message = 'Байгууллагуудын жагсаалтыг нээж үзэж байна';
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();

            $level = LogLevel::ERROR;
            $message = 'Байгууллагуудын жагсаалтыг нээж үзэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('organizations', $level ?? LogLevel::NOTICE, $message, $context);
        }
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
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }
    
    public function insert()
    {
        try {
            $context = ['model' => OrganizationModel::class];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            if (!$this->isUserCan('system_organization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new OrganizationModel($this->pdo);
            
            if ($is_submit) {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody['alias'])
                    || empty($parsedBody['name'])
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                $record = [
                    'name' => $parsedBody['name'],
                    'alias' => \preg_replace('/[^A-Za-z0-9_-]/', '', $parsedBody['alias'])
                ];
                $parent_id = \filter_var($parsedBody['parent_id'] ?? 0, \FILTER_VALIDATE_INT);
                if ($parent_id !== false && $parent_id > 0) {
                    $record['parent_id'] = $parent_id;
                }
                $context['record'] = $record;
                
                $id = $model->insert($record);
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $context['id'] = $id;
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/{$model->getName()}/$id");
                $file->allowImageOnly();
                $logo = $file->moveUploaded('logo', $model->getName());
                if ($logo) {
                    $model->updateById($id, ['logo' => $logo['path']]);
                    $context['logo'] = $logo;
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Байгууллага [{$record['name']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $this->twigTemplate(
                    \dirname(__FILE__) . '/organization-insert-modal.html',
                    ['parents' => $model->fetchAllPotentialParents()])->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Байгууллага үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Байгууллага үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('organizations', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => OrganizationModel::class];
            
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new OrganizationModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $record['rbac_users'] = $this->retrieveUsersDetail($record['created_by'], $record['updated_by']);
            $context['record'] = $record;
            if (!empty($record['parent_id'])) {
                $parent = $model->getById($record['parent_id']);
                if (empty($parent)) {
                    $parent['name'] = "- no parent or it's parent deleted -";
                }
                $record['parent_name'] = $parent['name'];
            }
                
            $this->twigTemplate(\dirname(__FILE__) . '/organization-retrieve-modal.html', ['record' => $record])->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['name']} байгууллагын мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Байгууллагын мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('organizations', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => OrganizationModel::class];
            
            if (!$this->isUserCan('system_organization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new OrganizationModel($this->pdo);
            $current = $model->getById($id);
            if (empty($current)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['alias'])
                    || empty($payload['name'])
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                $record = [
                    'name' => $payload['name'],
                    'alias' => \preg_replace('/[^A-Za-z0-9_-]/', '', $payload['alias'])
                ];
                
                if (isset($payload['parent_id'])) {
                    $parent_id = \filter_var($payload['parent_id'], \FILTER_VALIDATE_INT);
                    if ($parent_id !== false && $parent_id >= 0) {
                        $record['parent_id'] = $parent_id;
                    }
                }
                
                $context['record'] = $record;
                $context['record']['id'] = $id;
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/{$model->getName()}/$id");
                $file->allowImageOnly();
                $logo = $file->moveUploaded('logo', $model->getName());
                if ($logo) {
                    $record['logo'] = $logo['path'];
                }
                $current_logo_name = $current['logo'] ? '' : \basename($current['logo']);
                if (!empty($current_logo_name)) {
                    if ($file->getLastUploadError() == -1) {
                        $file->unlinkByName($current_logo_name, $model->getName());
                        $record['logo'] = '';
                    } elseif (isset($record['logo'])
                        && \basename($record['logo']) != $current_logo_name
                    ) {
                        $file->unlinkByName($current_logo_name, $model->getName());
                    }
                }
                if (isset($record['logo'])) {
                    $context['record']['logo'] = $record['logo'];
                }
                
                $updated = $model->updateById($id, $record);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "{$current['name']} байгууллагын мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $this->twigTemplate(
                    \dirname(__FILE__) . '/organization-update-modal.html',
                    [
                        'record' => $current,
                        'parents' => $model->fetchAllPotentialParents()
                    ]
                )->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $current;
                $message = "{$record['name']} байгууллагын мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = 'Байгууллагын мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('organizations', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => OrganizationModel::class];
            
            if (!$this->isUserCan('system_organization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !isset($payload['name'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);

            if ($this->getUser()->organization['id'] == $id) {
                throw new \Exception('Cannot remove currently active organization!', 403);
            } elseif ($id == 1) {
                throw new \Exception('Cannot remove first organization!', 403);
            }
            
            $deleted = (new OrganizationModel($this->pdo))->deleteById($id);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} байгууллагыг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Байгууллагыг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('organizations', $level, $message, $context);
        }
    }
}
