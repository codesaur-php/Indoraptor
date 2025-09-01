<?php

namespace Raptor\User;

use Twig\TwigFunction;

use Psr\Log\LogLevel;

use codesaur\Template\MemoryTemplate;

use Raptor\Authentication\ForgotModel;
use Raptor\Authentication\UserRequestModel;
use Raptor\File\FilesController;
use Raptor\Organization\OrganizationModel;
use Raptor\Organization\OrganizationUserModel;
use Raptor\RBAC\UserRole;
use Raptor\RBAC\Roles;
use Raptor\Content\ReferenceModel;
use Raptor\Mail\Mailer;

class UsersController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        try {
            $context = [];
                
            if (!$this->isUserCan('system_user_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/user-index.html');
            $dashboard->set('title', $this->text('users'));
            $dashboard->render();
            
            $level = LogLevel::NOTICE;
            $message = 'Хэрэглэгчдийн жагсаалтыг нээж үзэж байна';
        } catch (\Throwable $e) {
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчдийн жагсаалтыг нээж үзэх үед алдаа гарлаа';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            
            $this->dashboardProhibited("$message.<br/><br/>{$e->getMessage()}", $e->getCode())->render();
        } finally {
            $this->indolog('users', $level, $message, $context + ['model' => UsersModel::class]);
        }
    }
    
    public function list()
    {
        try {
            if (!$this->isUserCan('system_user_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $table = (new UsersModel($this->pdo))->getName();
            $users_infos = $this->query(
                "SELECT id,photo,last_name,first_name,username,phone,email,status FROM $table WHERE is_active=1"
            )->fetchAll();
            
            $users = [];
            foreach ($users_infos as $user) {
                $users[$user['id']] = $user;
            }
            
            $user_role_table = (new UserRole($this->pdo))->getName();
            $roles_table = (new Roles($this->pdo))->getName();
            $select_user_role =
                'SELECT t1.role_id, t1.user_id, t2.name, t2.alias ' . 
                "FROM $user_role_table as t1 INNER JOIN $roles_table as t2 ON t1.role_id=t2.id " .
                'WHERE t1.is_active=1';
            $user_role = $this->query($select_user_role)->fetchAll();
            \array_walk($user_role, function($value) use (&$users) {
                if (isset($users[$value['user_id']])) {
                    if (!isset($users[$value['user_id']]['roles'])) {
                        $users[$value['user_id']]['roles'] = [];
                    }
                    $users[$value['user_id']]['roles'][$value['role_id']] = "{$value['alias']}_{$value['name']}";
                }
            });
            
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $org_user_table = (new OrganizationUserModel($this->pdo))->getName();
            $select_orgs_users =
                'SELECT t1.user_id, t1.organization_id as id, t2.name, t2.alias ' .
                "FROM $org_user_table as t1 INNER JOIN $org_table as t2 ON t1.organization_id=t2.id " .
                'WHERE t1.is_active=1 AND t2.is_active=1';
            $org_users = $this->query($select_orgs_users)->fetchAll();
            \array_walk($org_users, function($value) use (&$users) {
                $user_id = $value['user_id'];
                unset($value['user_id']);
                if (isset($users[$user_id])) {
                    if (!isset($users[$user_id]['organizations'])) {
                        $users[$user_id]['organizations'] = [];
                    }
                    $users[$user_id]['organizations'][] = $value;
                }
            });
            
            $this->respondJSON([
                'status' => 'success',
                'list' => \array_values($users)
            ]);
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }
    
    public function insert()
    {
        try {
            $context = [];
            
            if (!$this->isUserCan('system_user_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new UsersModel($this->pdo);
            $orgModel = new OrganizationModel($this->pdo);
            if ($this->getRequest()->getMethod() == 'POST') {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody['username']) || empty($parsedBody['email'])
                    || \filter_var($parsedBody['email'], \FILTER_VALIDATE_EMAIL) === false
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                $record = [
                    'username' => $parsedBody['username'],
                    'first_name' => $parsedBody['first_name'] ?? null,
                    'last_name' => $parsedBody['last_name'] ?? null,
                    'phone' => $parsedBody['phone'] ?? null,
                    'email' => \filter_var($parsedBody['email'], \FILTER_VALIDATE_EMAIL)
                ];
                if (empty($parsedBody['password'])) {
                    $bytes = \random_bytes(10);
                    $password = \bin2hex($bytes);
                } else {
                    $password = $parsedBody['password'];
                }
                $record['password'] = \password_hash($password, \PASSWORD_BCRYPT);                
                $status = $parsedBody['status'] ?? 'off';
                $record['status'] = $status != 'on' ? 0 : 1;
                $insert = $model->insert($record);
                if ($insert == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                
                if (!empty($parsedBody['organization'] ?? null)) {
                    $organization = \filter_var($parsedBody['organization'], \FILTER_VALIDATE_INT);
                    if ($organization !== false
                        && !empty($orgModel->getById($organization))
                    ) {
                        (new OrganizationUserModel($this->pdo))
                            ->insert(['user_id' => $insert['id'], 'organization_id' => $organization]);
                    }
                }
                
                $file = new FilesController($this->getRequest());
                $file->setFolder("/{$model->getName()}/{$insert['id']}");
                $file->allowImageOnly();
                $photo = $file->moveUploaded('photo', $model->getName());
                if ($photo) {
                    $model->updateById($insert['id'], ['photo' => $photo['path']]);
                    $context += ['photo' => $photo];
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $context += ['id' => $insert['id'], 'record' => $record];
                $message = 'Хэрэглэгч үүсгэх үйлдлийг амжилттай гүйцэтгэлээ';
            } else {
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/user-insert.html',
                    ['organizations' => $orgModel->getRows()]
                );
                $dashboard->set('title', $this->text('create-new-user'));
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Хэрэглэгч үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгч үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('users', $level, $message, $context + ['model' => UsersModel::class]);
        }
    }
    
    public function update(int $id)
    {
        try {
            $context = [];
            
            if (!$this->isUserAuthorized()
                || (!$this->isUserCan('system_user_update')
                    && $this->getUserId() != $id)
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($id == 1 && $this->getUserId() != $id) {
                throw new \Exception('No one but root can edit this account!', 403);
            }
            
            $model = new UsersModel($this->pdo);
            $current = $model->getById($id);
            if (empty($current)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $orgModel = new OrganizationModel($this->pdo);
            $orgUserModel = new OrganizationUserModel($this->pdo);
            $rolesModel = new Roles($this->pdo);
            $userRoleModel = new UserRole($this->pdo);
            
            if ($this->getRequest()->getMethod() == 'PUT') {
                $parsedBody = $this->getParsedBody();
                $context['payload'] = $parsedBody;
                if (empty($parsedBody['username']) || empty($parsedBody['email'])
                    || \filter_var($parsedBody['email'], \FILTER_VALIDATE_EMAIL) === false
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                $record = [
                    'username' => $parsedBody['username'],
                    'first_name' => $parsedBody['first_name'] ?? null,
                    'last_name' => $parsedBody['last_name'] ?? null,
                    'phone' => $parsedBody['phone'] ?? null,
                    'email' => \filter_var($parsedBody['email'], \FILTER_VALIDATE_EMAIL)
                ];
                if (!empty($parsedBody['password'])) {
                    $record['password'] = \password_hash($parsedBody['password'], \PASSWORD_BCRYPT);
                }                
                $status = $parsedBody['status'] ?? 'off';
                $record['status'] = $status != 'on' ? 0 : 1;
                if ($id == 1 && $record['status'] == 0) {
                    // u can't deactivate root user!
                    unset($record['status']);
                }
                
                $context += ['record' => $record + ['id' => $id]];
                
                $existing_email = $model->getRowBy(['email' => $record['email']]);
                $existing_username = $model->getRowBy(['username' => $record['username']]);
                if (!empty($existing_username) && $existing_username['id'] != $id) {
                    throw new \Exception($this->text('user-exists') . " username => [{$record['username']}]", 403);
                } elseif (!empty($existing_email) && $existing_email['id'] != $id) {
                    throw new \Exception($this->text('user-exists') . " email => [{$record['email']}]", 403);
                }
                
                $file = new FilesController($this->getRequest());
                $file->setFolder("/{$model->getName()}/$id");
                $file->allowImageOnly();
                $photo = $file->moveUploaded('photo', $model->getName());
                if ($photo) {
                    $record['photo'] = $photo['path'];
                }
                $current_photo_file = empty($current['photo']) ? null : \basename($current['photo']);                
                if (!empty($current_photo_file)) {
                    if ($file->getLastUploadError() == -1) {
                        $file->deleteUnlink($current_photo_file, $model->getName());
                        $record['photo'] = '';
                    } elseif (!empty($record['photo'])
                        && \basename($record['photo']) != $current_photo_file
                    ) {
                        $file->deleteUnlink($current_photo_file, $model->getName());
                    }
                }                
                if (isset($record['photo'])) {
                    $context['photo'] = $record['photo'];
                }
                
                $updated = $model->updateById($id, $record);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $organizations = [];
                $post_organizations = \filter_var($parsedBody['organizations'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY);
                foreach ($post_organizations as $org_id) {
                    $organizations[$org_id] = true;
                }
                if ($this->isUserCan('system_user_organization_set')) {
                    $sql =
                        'SELECT t1.* ' .
                        "FROM {$orgUserModel->getName()} t1 INNER JOIN {$orgModel->getName()} t2 ON t1.organization_id=t2.id " .
                        "WHERE t1.user_id=$id AND t1.is_active=1 AND t2.is_active=1";
                    $userOrgs = $this->query($sql)->fetchAll();
                    foreach ($userOrgs as $row) {
                        if (isset($organizations[$row['organization_id']])) {
                            unset($organizations[$row['organization_id']]);
                        } elseif ($row['organization_id'] == 1 && $id == 1) {
                            // can't strip root user from system organization!
                        } else {
                            $orgUserModel->deleteById($row['id']);
                            $this->indolog(
                                'users',
                                LogLevel::ALERT,
                                "{$row['organization_id']} дугаартай байгууллагын хэрэглэгчийн бүртгэлээс {$record['username']} хэрэглэгчийг хаслаа",
                                ['action' => 'organization-strip', 'user_id' => $id, 'organization_id' => $row['organization_id']]
                            );
                        }
                    }
                    foreach (\array_keys($organizations) as $org_id) {
                        if ($orgUserModel->insert(['user_id' => $id, 'organization_id' => $org_id])) {
                            $this->indolog(
                                'users',
                                LogLevel::ALERT,
                                "{$record['username']} хэрэглэгчийг $org_id дугаар бүхий байгууллагад нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                                ['action' => 'organization-set', 'user_id' => $id, 'organization_id' => $org_id]
                            );
                        }
                    }
                }
                
                if ($this->isUserCan('system_rbac')) {
                    $post_roles = \filter_var($parsedBody['roles'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY);
                    $roles = [];
                    foreach ($post_roles as $role) {
                        $roles[$role] = true;
                    }

                    $user_role = $userRoleModel->fetchAllRolesByUser($id);
                    foreach ($user_role as $row) {
                        if (isset($roles[$row['role_id']])) {
                            unset($roles[$row['role_id']]);
                        } elseif ($row['role_id'] == 1 && $id == 1) {
                            // can't delete root user's coder role!
                        } elseif ($row['role_id'] == 1 && !$this->isUser('system_coder')) {
                            // only coder can strip another coder role
                        } else {
                            $userRoleModel->deleteById($row['id']);
                            $this->indolog(
                                'users',
                                LogLevel::ALERT,
                                "{$record['username']} хэрэглэгчээс {$row['id']} дугаар бүхий дүрийг хаслаа",
                                ['action' => 'role-strip', 'user_id' => $id, 'role_id' => $row['role_id']]
                            );
                        }
                    }
                    
                    foreach (\array_keys($roles) as $role_id) {
                        if ($role_id == 1 && (
                            !$this->isUser('system_coder') || $this->getUserId() != 1)
                        ) {
                            // only root coder can add another coder role
                            continue;
                        }
                        if ($userRoleModel->insert(['user_id' => $id, 'role_id' => $role_id])) {
                            $this->indolog(
                                'users',
                                LogLevel::ALERT,
                                "{$record['username']} хэрэглэгч дээр $role_id дугаар бүхий дүр нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                                ['action' => 'role-set', 'user_id' => $id, 'role_id' => $role_id]
                            );
                        }
                    }
                }
                
                $this->respondJSON([ 
                    'type' => 'primary',
                    'status' => 'success',
                    'message' => $this->text('record-update-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "{$record['username']} хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $organizations = $orgModel->getRows();
                $vars = ['record' => $current, 'organizations' => $organizations];
                $select_org_ids =
                    'SELECT ou.organization_id as id ' .
                    "FROM {$orgUserModel->getName()} as ou INNER JOIN {$orgModel->getName()} as o ON ou.organization_id=o.id " .
                    "WHERE ou.user_id=$id AND ou.is_active=1 AND o.is_active=1";
                $org_ids = $this->query($select_org_ids)->fetchAll();
                $ids = [];
                foreach ($org_ids as $org) {
                    $ids[] = $org['id'];
                }
                $vars['current_organizations'] = $ids;
                
                $rbacs = ['common' => 'Common'];
                $alias_names = $this->query(
                    "SELECT alias,name FROM {$orgModel->getName()} WHERE alias!='common' AND is_active=1 ORDER BY id desc"
                )->fetchAll();
                foreach ($alias_names as $row) {
                    if (isset($rbacs[$row['alias']])) {
                        $rbacs[$row['alias']] .= ", {$row['name']}";
                    } else {
                        $rbacs[$row['alias']] = $row['name'];
                    }
                }
                $vars['rbacs'] = $rbacs;

                $roles = \array_map(function() { return []; }, \array_flip(\array_keys($rbacs)));
                $rbac_roles = $this->query(
                    "SELECT id,alias,name,description FROM {$rolesModel->getName()} WHERE is_active=1"
                )->fetchAll();
                \array_walk($rbac_roles, function($value) use (&$roles) {
                    if (!isset($roles[$value['alias']])) {
                        $roles[$value['alias']] = [];
                    }
                    $roles[$value['alias']][$value['id']] = [$value['name']];

                    if (!empty($value['description'])) {
                        $roles[$value['alias']][$value['id']][] = $value['description'];
                    }
                });
                $vars['roles'] = $roles;

                $select_user_roles =
                    "SELECT rur.role_id FROM {$userRoleModel->getName()} as rur INNER JOIN {$rolesModel->getName()} as rr ON rur.role_id=rr.id " .
                    "WHERE rur.user_id=$id AND rur.is_active=1 AND rr.is_active=1";
                $current_roles = $this->query($select_user_roles)->fetchAll();
                $current_role = [];
                foreach ($current_roles as $row) {
                    $current_role[] = $row['role_id'];
                }
                $vars['current_role'] = $current_role;
                
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/user-update.html', $vars);
                $dashboard->set('title', $this->text('edit-user'));
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $context += [
                    'record' => $current,
                    'current_role' => $vars['current_role'],
                    'current_organizations' => $vars['current_organizations']
                ];
                $message = "{$current['username']} хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг эхлүүллээ";
            }
        } catch (\Throwable $e) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('users', $level, $message, $context + ['model' => UsersModel::class]);
        }
    }
    
    public function view(int $id)
    {
        try {
            if (!$this->isUserAuthorized()
                || (!$this->isUserCan('system_user_index')
                && $this->getUserId() != $id)
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new UsersModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $record['rbac_users'] = $this->retrieveUsersDetail($record['created_by'], $record['updated_by']);
            
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $org_user_table = (new OrganizationUserModel($this->pdo))->getName();
            $select_user_orgs =
                'SELECT t2.name, t2.alias, t2.id ' .
                "FROM $org_user_table as t1 INNER JOIN $org_table as t2 ON t1.organization_id=t2.id " .
                "WHERE t1.is_active=1 AND t2.is_active=1 AND t1.user_id=$id";
            $organizations = $this->query($select_user_orgs)->fetchAll();

            $roles_table = (new Roles($this->pdo))->getName();
            $user_role_table = (new UserRole($this->pdo))->getName();
            $select_user_roles =
                'SELECT CONCAT(t2.alias, "_", t2.name) as name ' . 
                "FROM $user_role_table as t1 INNER JOIN $roles_table as t2 ON t1.role_id=t2.id " .
                "WHERE t1.is_active=1 AND t1.user_id=$id";
            $user_roles = $this->query($select_user_roles)->fetchAll();
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/user-view.html',
                ['record' => $record, 'roles' => $user_roles, 'organizations' => $organizations]
            );
            $dashboard->set('title', $this->text('user'));
            $dashboard->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['username']} хэрэглэгчийн мэдээллийг нээж үзэж байна";
            $context = ['record' => $record, 'roles' => $user_roles, 'organizations' => $organizations];
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();

            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийн мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
            $context = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('users', $level, $message, $context + ['model' => UsersModel::class]);
        }
    }
    
    public function delete()
    {
        try {
            if (!$this->isUserCan('system_user_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            $context = ['payload' => $payload];
            if (!isset($payload['id'])
                || !isset($payload['name'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            
            if ($this->getUserId() == $id) {
                throw new \Exception('Cannot suicide myself :(', 403);
            } elseif ($id == 1) {
                throw new \Exception('Cannot remove first acccount!', 403);
            }
            
            $deleted = (new UsersModel($this->pdo))->deleteById($id);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} хэрэглэгчийг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчийг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('users', $level, $message, $context + ['model' => UsersModel::class]);
        }
    }
    
    public function requestsModal(string $table)
    {
        try {
            if (!$this->isUserCan('system_user_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            if (!\in_array($table, ['forgot', 'newbie'])) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            
            if ($table == 'forgot') {
                $model = new ForgotModel($this->pdo);
                $message = 'Нууц үгээ сэргээх хүсэлтүүдийн жагсаалтыг нээж үзэж байна';
            } else {
                $model = new UserRequestModel($this->pdo);
                $message = 'Шинэ хэрэглэгчээр бүртгүүлэх хүсэлтүүдийн жагсаалтыг нээж үзэж байна';
            }
            $vars = [
                'rows' => $model->getRows(['WHERE' => 'is_active!=999', 'ORDER BY' => 'created_at desc'])
            ];
            
            $template = $this->twigTemplate(\dirname(__FILE__) . "/$table-index-modal.html", $vars);
            $template->addFunction(new TwigFunction('isExpired', function (string $date, int $minutes = 5): bool
            {
                $now_date = new \DateTime();
                $then = new \DateTime($date);
                $diff = $then->diff($now_date);
                return $diff->y > 0 || $diff->m > 0 || $diff->d > 0 || $diff->h > 0 || $diff->i > $minutes;
            }));
            $template->render();
            
            $level = LogLevel::NOTICE;
            $context = ['model' => \get_class($model), 'table' => $table];
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();

            $level = LogLevel::ERROR;
            $message = "Хэрэглэгчдийн мэдээллийн хүснэгт [$table] нээж үзэх хүсэлт алдаатай байна";
            $context = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    public function requestApprove()
    {
        try {
            $context = ['action' => 'user-request-approve'];
            
            if (!$this->isUserCan('system_user_insert')) {
                throw new \Exception('No permission for an action [approval]!', 401);
            }
            
            $parsedBody = $this->getParsedBody();
            $id = $parsedBody['id'] ?? null;
            if (empty($id)
                || !\filter_var($id, \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $context += ['payload' => $parsedBody, 'id' => $id];
            
            $requestsModel = new UserRequestModel($this->pdo);
            $record = $requestsModel->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $model = new UsersModel($this->pdo);
            $existing = $this->prepare("SELECT id FROM {$model->getName()} WHERE username=:username OR email=:email");            
            $existing->bindParam(':email', $record['email'], \PDO::PARAM_STR, $model->getColumn('email')->getLength());
            $existing->bindParam(':username', $record['username'], \PDO::PARAM_STR, $model->getColumn('username')->getLength());
            if ($existing->execute() && !empty($existing->fetch())) {
                throw new \Exception($this->text('user-exists') . ": username/email => {$record['username']}/{$record['email']}", 403);
            }
            
            unset($record['id']);
            unset($record['user_id']);
            unset($record['status']);
            unset($record['is_active']);
            unset($record['created_at']);
            unset($record['created_by']);
            unset($record['updated_at']);
            unset($record['updated_by']);
            $user_id = $model->insert($record);
            if ($user_id == false) {
                throw new \Exception('Failed to create user');
            }
            $context += ['user' => $record + ['id' => $user_id]];
            
            $requestsModel->updateById($id, ['is_active' => 0, 'status' => 2, 'user_id' => $user_id]);
            
            $organization_id = \filter_var($parsedBody['organization_id'] ?? 0, \FILTER_VALIDATE_INT);
            if (!$organization_id) {
                $organization_id = 1;
            }
            $context += ['organization' => $organization_id];
            $user_org_added = (new OrganizationUserModel($this->pdo))->insert(
                ['user_id' => $user_id, 'organization_id' => $organization_id]
            );
            
            $code = $this->getLanguageCode();
            $referenceModel = new ReferenceModel($this->pdo);
            $referenceModel->setTable('templates');
            $reference = $referenceModel->getRowBy(
                [
                    'c.code' => $code,
                    'p.keyword' => 'approve-new-user',
                    'p.is_active' => 1
                ]
            );
            if (!empty($reference['content'])) {
                $content = $reference['content'];
                $template = new MemoryTemplate();
                $template->source($content['full'][$code]);
                $template->set('email', $record['email']);
                $template->set('login', $this->generateRouteLink('login', [], true));
                $template->set('username', $record['username']);
                (new Mailer($this->pdo))
                    ->mail($record['email'], null, $content['title'][$code], $template->output())
                    ->send();
            }
            
            $result = $model->getById($user_id);
            if ($user_org_added != false) {
                $result['organizations'] = [(new OrganizationModel($this->pdo))->getById($organization_id)];
            }            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-insert-success'),
                'record' => $result
            ]);
            
            $level = LogLevel::ALERT;
            $message = "Шинэ бүртгүүлсэн {$record['username']} нэртэй [{$record['email']}] хаягтай хэрэглэгчийн хүсэлтийг зөвшөөрч системд нэмлээ";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());

            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг зөвшөөрч системд нэмэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('users', $level, $message, $context + ['model' => UserRequestModel::class]);
        }
    }
    
    public function requestDelete()
    {
        try {
            $context = ['action' => 'user-request-delete', 'table' => 'newbie'];
            
            if (!$this->isUserCan('system_user_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !isset($payload['name'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $context += ['payload' => $payload];
            
            $deleted = (new UserRequestModel($this->pdo))->deleteById((int)$payload['id']);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} хэрэглэгчээр бүртгүүлэх хүсэлтийг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('users', $level, $message, $context + ['model' => UserRequestModel::class]);
        }
    }
    
    public function setUserRole()
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            $context = ['action' => 'set-user-role'];
            
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $params = $this->getQueryParams();
            if (empty($params['id'])
                || \filter_var($params['id'], \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = (int) $params['id'];
            $context['id'] = $id;
            
            $model = new UsersModel($this->pdo);
            $user = $model->getById($id);
            if (empty($user)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $context['user'] = $user;
            $userRoleModel = new UserRole($this->pdo);
            
            if ($is_submit) {
                $post_roles = \filter_var($this->getParsedBody()['roles'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY);
                $roles = [];
                foreach ($post_roles as $role) {
                    $roles[$role] = true;
                }
                if ((empty($roles) || !\array_key_exists(1, $roles)) && $id == 1) {
                    throw new \Exception('Default user must have a role', 403);
                }

                $user_role = $userRoleModel->fetchAllRolesByUser($id);
                foreach ($user_role as $row) {
                    if (isset($roles[$row['role_id']])) {
                        unset($roles[$row['role_id']]);
                    } elseif ($row['role_id'] == 1 && $id == 1) {
                        // can't delete root account's coder role!
                    } elseif ($row['role_id'] == 1 && !$this->isUser('system_coder')) {
                        // only coder can strip another coder role
                    } else {
                        $userRoleModel->deleteById($row['id']);
                        $this->indolog(
                            'users',
                            LogLevel::ALERT,
                            "{$user['username']} хэрэглэгчээс {$row['id']} дугаар бүхий дүрийг хаслаа",
                            ['action' => 'role-strip', 'user_id' => $id, 'role_id' => $row['role_id']]
                        );
                    }
                }
                
                foreach (\array_keys($roles) as $role_id) {
                    if ($role_id == 1 && (
                        !$this->isUser('system_coder') || $this->getUserId() != 1)
                    ) {
                        // only root coder can add another coder role
                        continue;
                    }
                    if ($userRoleModel->insert(['user_id' => $id, 'role_id' => $role_id])) {
                        $this->indolog(
                            'users',
                            LogLevel::ALERT,
                            "{$user['username']} хэрэглэгч дээр $role_id дугаар бүхий дүр нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                            ['action' => 'role-set', 'user_id' => $id, 'role_id' => $role_id]
                        );
                    }
                }

                return $this->respondJSON([
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                $vars = ['profile' => $user];
                $rbacs = ['common' => 'Common'];
                $org_table = (new OrganizationModel($this->pdo))->getName();
                $organizations_result = $this->query(
                    "SELECT alias,name FROM $org_table WHERE alias!='common' AND is_active=1 ORDER BY id desc"
                )->fetchAll();
                foreach ($organizations_result as $row) {
                    if (isset($rbacs[$row['alias']])) {
                        $rbacs[$row['alias']] .= ", {$row['name']}";
                    } else {
                        $rbacs[$row['alias']] = $row['name'];
                    }
                }
                $vars['rbacs'] = $rbacs;

                $roles_table = (new Roles($this->pdo))->getName();
                $roles = \array_map(function() { return []; }, \array_flip(\array_keys($rbacs)));
                $roles_result = $this->query("SELECT id,alias,name,description FROM $roles_table WHERE is_active=1")->fetchAll();
                \array_walk($roles_result, function($value) use (&$roles) {
                    if (!isset($roles[$value['alias']])) {
                        $roles[$value['alias']] = [];
                    }
                    $roles[$value['alias']][$value['id']] = [$value['name']];

                    if (!empty($value['description'])) {
                        $roles[$value['alias']][$value['id']][] = $value['description'];
                    }
                });
                $vars['roles'] = $roles;

                $current_role = [];
                $select_current_roles =
                    "SELECT rur.role_id FROM {$userRoleModel->getName()} as rur INNER JOIN $roles_table as rr ON rur.role_id=rr.id " .
                    "WHERE rur.user_id=$id AND rur.is_active=1 AND rr.is_active=1";
                $current_roles = $this->query($select_current_roles)->fetchAll();
                foreach ($current_roles as $row) {
                    $current_role[] = $row['role_id'];
                }
                $vars['current_role'] = $current_role;
                
                $this->twigTemplate(\dirname(__FILE__) . '/user-set-role-modal.html', $vars)->render();

                $this->indolog(
                    'users',
                    LogLevel::NOTICE,
                    "$id дугаартай  {$user['username']} хэрэглэгчийн дүрийг тохируулах үйлдлийг эхлүүллээ",
                    $context
                );
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $e->getMessage()
                ], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $this->indolog(
                'users',
                LogLevel::ERROR,
                ($user['username'] ?? (($id ?? 'үл мэдэгдэх') . ' дугаартай')) . ' хэрэглэгчийн дүрийг тохируулах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо',
                $context
            );
        }
    }
    
    public function setOrganization()
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            $context = ['action' => 'organization-user-set'];
            
            if (!$this->isUserCan('system_user_organization_set')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $params = $this->getQueryParams();
            if (empty($params['user_id'])
                || \filter_var($params['user_id'], \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $user_id = (int) $params['user_id'];
            
            $model = new UsersModel($this->pdo);
            $user = $model->getById($user_id);
            if (empty($user)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $context['user'] = $user;
            
            $orgModel = new OrganizationModel($this->pdo);
            $orgUserModel = new OrganizationUserModel($this->pdo);
            
            if ($is_submit) {
                $organizations = [];
                $post_organizations = \filter_var($this->getParsedBody()['organizations'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY);
                foreach ($post_organizations as $id) {
                    $organizations[$id] = true;
                }
                if ($user_id == 1
                    && (empty($organizations) || !\array_key_exists(1, $organizations))
                ) {
                    throw new \Exception('Default user must belong to an organization', 503);
                }

                $sql =
                    'SELECT t1.* ' .
                    "FROM {$orgUserModel->getName()} t1 INNER JOIN {$orgModel->getName()} t2 ON t1.organization_id=t2.id " .
                    "WHERE t1.user_id=$user_id AND t1.is_active=1 AND t2.is_active=1";
                $user_orgs = $this->query($sql)->fetchAll();
                foreach ($user_orgs as $row) {
                    if (isset($organizations[$row['organization_id']])) {
                        unset($organizations[$row['organization_id']]);
                    } elseif ($row['organization_id'] == 1 && $id == 1) {
                            // can't strip root user from system organization!
                    } else {
                        $orgUserModel->deleteById($row['id']);
                        $this->indolog(
                            'users',
                            LogLevel::ALERT,
                            "{$row['organization_id']} дугаартай байгууллагын хэрэглэгчийн бүртгэлээс {$user['username']} хэрэглэгчийг хаслаа",
                            ['action' => 'organization-strip', 'user_id' => $user_id, 'organization_id' => $row['organization_id']]
                        );
                    }
                }
                foreach (\array_keys($organizations) as $org_id) {
                    if ($orgUserModel->insert(['user_id' => $user_id, 'organization_id' => $org_id])) {
                        $this->indolog(
                            'users',
                            LogLevel::ALERT,
                            "{$user['username']} хэрэглэгчийг $org_id дугаар бүхий байгууллагад нэмэх үйлдлийг амжилттай гүйцэтгэлээ",
                            ['action' => 'organization-set', 'user_id' => $user_id, 'organization_id' => $org_id]
                        );
                    }
                }

                return $this->respondJSON([
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                $response = $this->query(
                    'SELECT ou.organization_id as id ' .
                    "FROM {$orgUserModel->getName()} as ou INNER JOIN {$orgModel->getName()} as o ON ou.organization_id=o.id " .
                    "WHERE ou.user_id=$user_id AND ou.is_active=1 AND o.is_active=1"
                );
                $current_organizations = [];
                foreach ($response as $org) {
                    $current_organizations[] = $org['id'];
                }

                $vars = [
                    'profile' => $user,
                    'organizations' => $orgModel->getRows(),
                    'current_organizations' => $current_organizations
                ];
                $this->twigTemplate(\dirname(__FILE__) . '/user-set-organization-modal.html', $vars)->render();

                $context['current_organizations'] = $current_organizations;
                $this->indolog(
                    'users',
                    LogLevel::NOTICE,
                    "{$user['username']} хэрэглэгчийн байгууллагын мэдээллийг өөрчлөх үйлдлийг эхлүүллээ",
                    $context
                );
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $e->getMessage()
                ], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $this->indolog(
                'users',
                LogLevel::ERROR,
                ($user['username'] ?? (($user_id ?? 'үл мэдэгдэх') . ' дугаартай')) . ' хэрэглэгчийн байгууллагын мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо',
                $context
            );
        }
    }
}
