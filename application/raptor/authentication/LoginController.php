<?php

namespace Raptor\Authentication;

use Psr\Log\LogLevel;
use Fig\Http\Message\StatusCodeInterface;

use codesaur\Template\MemoryTemplate;

use Raptor\User\UsersModel;
use Raptor\Organization\OrganizationModel;
use Raptor\Organization\OrganizationUserModel;
use Raptor\Content\ReferenceModel;
use Raptor\Mail\Mailer;

\define('CODESAUR_PASSWORD_RESET_MINUTES', $_ENV['CODESAUR_PASSWORD_RESET_MINUTES'] ?? 10);

class LoginController extends \Raptor\Controller
{
    public function index()
    {
        $forgot_id = $this->getQueryParams()['forgot'] ?? false;
        if (!empty($forgot_id)) {
            return $this->forgotPassword($forgot_id);
        }
        
        if ($this->isUserAuthorized()) {
            return $this->redirectTo('home');
        }
        
        $vars = (array)$this->getAttribute('settings');
        $reference = new ReferenceModel($this->pdo);
        $reference->setTable('templates');
        $rows = $reference->getRows([
            'WHERE' => "c.code='{$this->getLanguageCode()}' AND (p.keyword='tos' OR p.keyword='pp') AND p.is_active=1"
        ]);
        foreach ($rows as $row) {
            $vars[$row['keyword']] = $row['localized'];
        }
        
        $login = $this->twigTemplate(\dirname(__FILE__) . '/login.html', $vars);
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $login->set($key, $value);
        }
        $login->render();
    }
    
    public function entry()
    {
        try {
            $payload = $this->getParsedBody();
            if ($this->isUserAuthorized() || empty($payload['username']) || empty($payload['password'])) {
                throw new \Exception($this->text('invalid-request'), StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            $users = new UsersModel($this->pdo);
            $stmt = $users->prepare("SELECT * FROM {$users->getName()} WHERE (username=:usr OR email=:eml) AND is_active=1 LIMIT 1");
            $stmt->bindParam(':eml', $payload['username'], \PDO::PARAM_STR, $users->getColumn('email')->getLength());
            $stmt->bindParam(':usr', $payload['username'], \PDO::PARAM_STR, $users->getColumn('username')->getLength());
            if (!$stmt->execute() || $stmt->rowCount() != 1) {
                throw new \Exception('Invalid username or password', StatusCodeInterface::STATUS_UNAUTHORIZED);
            }
            $user = $stmt->fetch();
            if (!\password_verify($payload['password'], $user['password'])) {
                throw new \Exception('Invalid username or password', StatusCodeInterface::STATUS_UNAUTHORIZED);
            }            
            if (((int) $user['status']) != 1) {
                throw new \Exception('Inactive user', StatusCodeInterface::STATUS_FORBIDDEN);
            }
            
            $org_model = new OrganizationModel($this->pdo);
            $org_user_model = new OrganizationUserModel($this->pdo);
            $stmt_user_org = $this->prepare(
                'SELECT t1.* ' .
                "FROM {$org_user_model->getName()} t1 INNER JOIN {$org_model->getName()} t2 ON t1.organization_id=t2.id " .
                'WHERE t1.user_id=:id AND t1.status=:status AND t1.is_active=1 AND t2.is_active=1 LIMIT 1'
            );
            $stmt_user_org->execute([':id' => $user['id'], ':status' => 1]);
            if ($stmt_user_org->rowCount() == 1) {
                $userOrg = $stmt_user_org->fetch();
            } else {
                $stmt_user_org->execute([':id' => $user['id'], ':status' => 0]);
                if ($stmt_user_org->rowCount() == 1) {
                    $userOrg = $stmt_user_org->fetch();
                    $org_user_model->updateById($userOrg['id'], ['status' => 1]);
                } else {
                    throw new \Exception('User doesn\'t belong to an organization', StatusCodeInterface::STATUS_NOT_ACCEPTABLE);
                }
            }
            
            $login_info = [
                'user_id' => $user['id'],
                'organization_id' => $userOrg['organization_id']
            ];
            $_SESSION['RAPTOR_JWT'] = (new JWTAuthMiddleware())->generate($login_info);
            
            $this->respondJSON(['status' => 'success', 'message' => "Хэрэглэгч {$user['first_name']} системд нэвтрэв."]);

            if (empty($user['code'])) {
                $users->updateById($user['id'], ['code' => $this->getLanguageCode()]);
            } elseif ($user['code'] != $this->getLanguageCode()
                && isset($this->getLanguages()[$user['code']])
            ) {
                $_SESSION['RAPTOR_LANGUAGE_CODE'] = $user['code'];
            }
            
            $this->indolog(
                'dashboard',
                LogLevel::INFO,
                'Хэрэглэгч {auth_user.first_name} {auth_user.last_name} системд нэвтрэв',
                [
                    'reason' => 'login',
                    'auth_user' => $user
                ]
            );
        } catch (\Throwable $e) {
            if (isset($_SESSION['RAPTOR_JWT'])) {
                unset($_SESSION['RAPTOR_JWT']);
            }
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());

            $this->errorLog($e);
            
            $this->indolog(
                'dashboard',
                LogLevel::ERROR,
                $e->getMessage(),
                [
                    'reason' => 'login',
                    'error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]
                ]
            );
        }
    }

    public function logout()
    {
        if (isset($_SESSION['RAPTOR_JWT'])) {
            $log_message = 'Хэрэглэгч {auth_user.first_name} {auth_user.last_name} системээс гарлаа';
            $log_context = ['reason' => 'logout', 'jwt' => $_SESSION['RAPTOR_JWT']];
            
            unset($_SESSION['RAPTOR_JWT']);
            
            $this->indolog('dashboard', LogLevel::NOTICE, $log_message, $log_context);
        }
        
        $this->redirectTo('home');
    }
    
    public function signup()
    {
        try {
            $log_context = ['reason' => 'request-new-user'];
            
            $payload = $this->getParsedBody();
            $log_context += ['payload' => $payload];
            if (isset($payload['password'])) {
                $password = $payload['password'];
                unset($log_context['payload']['password']);
            } else {
                $password = '';
            }
            if (isset($payload['password_re'])) {
                $passwordRe = $payload['password_re'];
                unset($log_context['payload']['password_re']);
            } else {
                $passwordRe = '';
            }
            if (empty($password) || $password != $passwordRe) {
                throw new \InvalidArgumentException($this->text('invalid-request'), StatusCodeInterface::STATUS_BAD_REQUEST);
            } else {
                unset($payload['password_re']);
            }
            $payload['password'] = \password_hash($password, \PASSWORD_BCRYPT);
            
            $code = $this->getLanguageCode();
            $referenceModel = new ReferenceModel($this->pdo);
            $referenceModel->setTable('templates');
            $reference = $referenceModel->getRowBy(
                [
                    'c.code' => $code,
                    'p.keyword' => 'request-new-user',
                    'p.is_active' => 1
                ]
            );
            if (empty($reference['localized'])) {
                throw new \Exception($this->text('email-template-not-set'), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            $content = $reference['localized'];
            
            if (empty($payload['email']) || empty($payload['username'])) {
                throw new \InvalidArgumentException('Invalid payload', StatusCodeInterface::STATUS_BAD_REQUEST);
            } elseif (\filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('Please provide valid email address.', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            $users = new UsersModel($this->pdo);
            if (!empty($users->getRowBy(['email' => $payload['email']]))) {
                throw new \Exception("Бүртгэлтэй [{$payload['email']}] хаягаар шинэ хэрэглэгч үүсгэх хүсэлт ирүүллээ. Татгалзав.", StatusCodeInterface::STATUS_FORBIDDEN);
            } elseif (!empty($users->getRowBy(['username' => $payload['username']]))) {
                throw new \Exception("Бүртгэлтэй [{$payload['username']}] хэрэглэгчийн нэрээр шинэ хэрэглэгч үүсгэх хүсэлт ирүүллээ. Татгалзав.", StatusCodeInterface::STATUS_FORBIDDEN);
            }
            
            $userRequest = new UserRequestModel($this->pdo);
            if (!empty($userRequest->getRowBy(
                [
                    'email' => $payload['email'],
                    'status' => 1, 'is_active' => 1
                ]))
            ) {
                throw new \Exception("Шинээр [{$payload['email']}] хаягтай хэрэглэгч үүсгэх хүсэлт ирүүлсэн боловч, уг мэдээллээр урьд нь хүсэлт өгч байсныг бүртгэсэн байсан учир дахин хүсэлт бүртгэхээс татгалзав.", StatusCodeInterface::STATUS_FORBIDDEN);
            } elseif (!empty($userRequest->getRowBy(
                [
                    'username' => $payload['username'],
                    'status' => 1, 'is_active' => 1
                ]))
            ) {
                throw new \Exception("Шинээр [{$payload['username']}] нэртэй хэрэглэгч үүсгэх хүсэлт ирүүлсэн боловч, уг мэдээллээр урьд нь хүсэлт өгч байсныг бүртгэсэн байсан учир дахин хүсэлт бүртгэхээс татгалзав.", StatusCodeInterface::STATUS_FORBIDDEN);
            }
            
            $profile = $userRequest->insert($payload);
            if (empty($profile)) {
                throw new \Exception("Шинээр [{$payload['username']}] нэртэй [{$payload['email']}] хаягтай хэрэглэгч үүсгэх хүсэлт ирүүлснийг мэдээлллийн санд бүртгэн хадгалах үйлдэл гүйцэтгэх явцад алдаа гарч зогслоо.", StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            
            $template = new MemoryTemplate();
            $template->set('email', $profile['email']);
            $template->set('username', $profile['username']);
            $template->source($content['content'][$code]);
            if ((new Mailer($this->pdo))
                    ->mail($profile['email'], null, $content['title'][$code], $template->output())
                    ->send()
            ) {
                $this->respondJSON(['status' => 'success', 'message' => $this->text('to-complete-registration-check-email')]);
            } else {
                $this->respondJSON(['status' => 'success', 'message' => 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг хүлээн авлаа!']);
            }
            
            $log_level = LogLevel::ALERT;
            $log_message = '{payload.username} нэртэй {payload.email} хаягтай шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүллээ.';
        } catch (\Throwable $e) {
            $log_message = $e->getMessage();
            $this->respondJSON(['message' => '<span class="text-secondary">Шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүлэх үед алдаа гарч зогслоо.</span><br/>' . $log_message], $e->getCode());
            
            $log_level = LogLevel::ERROR;
            $log_context += ['error' => ['code' => $e->getCode(), 'message' => $log_message]];
        } finally {
            $log_context['auth_user'] = [];
            $this->indolog('dashboard', $log_level ?? LogLevel::NOTICE, $log_message ?? 'request-new-user', $log_context);
        }
    }
    
    public function forgot()
    {
        try {
            $payload = $this->getParsedBody();
            $log_context = ['reason' => 'login-forgot', 'payload' => $payload];
            if (empty($payload['email'])
                || \filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false
            ) {
                throw new \InvalidArgumentException('Please provide valid email address', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            if (empty($payload['code'])) {
                $payload['code'] = $this->getLanguageCode();
            }
            
            $code = $this->getLanguageCode();
            $referenceModel = new ReferenceModel($this->pdo);
            $referenceModel->setTable('templates');
            $reference = $referenceModel->getRowBy(
                [
                    'c.code' => $code,
                    'p.keyword' => 'forgotten-password-reset',
                    'p.is_active' => 1
                ]
            );
            if (empty($reference['localized'])) {
                throw new \Exception($this->text('email-template-not-set'), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            $content = $reference['localized'];
            
            
            $users = new UsersModel($this->pdo);
            $user = $users->getRowBy(['email' => $payload['email']]);
            if (empty($user)) {
                throw new \Exception("Бүртгэлгүй [{$payload['email']}] хаяг дээр нууц үг шинээр тааруулах хүсэлт илгээхийг оролдлоо. Татгалзав.", StatusCodeInterface::STATUS_NOT_FOUND);
            }
            if ($user['is_active'] != 1) {
                throw new \Exception("Эрх нь нээгдээгүй хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт илгээх оролдлого хийв. Татгалзав.", StatusCodeInterface::STATUS_FORBIDDEN);
            }
            
            $forgot = new ForgotModel($this->pdo);
            $request = $forgot->insert([
                'status'          => 1,
                'forgot_password' => \uniqid('forgot'),
                'email'           => $user['email'],
                'code'            => $code,
                'user_id'         => $user['id'],
                'username'        => $user['username'],
                'last_name'       => $user['last_name'],
                'first_name'      => $user['first_name'],
                'remote_addr'     => $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? ''
            ]);
            if (!$request) {
                throw new \Exception("Хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт илгээснийг мэдээлллийн санд бүртгэн хадгалах үйлдэл гүйцэтгэх явцад алдаа гарч зогслоо.", StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            
            $log_level = LogLevel::INFO;
            $log_message = "{$payload['email']} хаягтай хэрэглэгч  нууц үгээ шинээр тааруулах хүсэлт илгээснийг бүртгэлээ";
            $log_context += ['forgot' => $request];

            $template = new MemoryTemplate();
            $template->set('email', $payload['email']);
            $template->set('minutes', CODESAUR_PASSWORD_RESET_MINUTES);
            $template->set('link', "{$this->generateRouteLink('login', [], true)}?forgot={$request['forgot_password']}");
            $template->source($content['content'][$code]);
            if ((new Mailer($this->pdo))
                    ->mail($payload['email'], null, $content['title'][$code], $template->output())
                    ->send()
            ) {
                $this->respondJSON(['status' => 'success', 'message' => $this->text('reset-email-sent')]);
            } else {
                $this->respondJSON(['status' => 'success', 'message' => $log_message]);
            }
        } catch (\Throwable $e) {
            $log_message = $e->getMessage();
            $this->respondJSON(['message' => '<span class="text-secondary">Хэрэглэгч нууц үгээ шинэчлэх хүсэлт илгээх үед алдаа гарч зогслоо.</span><br/>' . $log_message], $e->getCode());

            $log_level = LogLevel::ERROR;
            $log_context += ['error' => ['code' => $e->getCode(), 'message' => $log_message]];
        } finally {
            $log_context['auth_user'] = [];
            $this->indolog('dashboard', $log_level ?? LogLevel::NOTICE, $log_message ?? 'login-forgot', $log_context);
        }
    }
    
    public function forgotPassword(string $forgot_password)
    {
        try {   
            $log_context = [
                'reason' => 'forgot-password',
                'forgot_password' => $forgot_password
            ];
            
            $vars = (array)$this->getAttribute('settings');
            $vars['forgot_password'] = $forgot_password;
            
            $model = new ForgotModel($this->pdo);
            $forgot = $model->getRowBy([
                'forgot_password' => $forgot_password,
                'status' => 1,
                'is_active' => 1
            ]);
            if (empty($forgot)) {
                throw new \Exception('Not found', 404);
            }
            
            $code = $forgot['code'];
            if ($code != $this->getLanguageCode()) {
                if (isset($this->getLanguages()[$code])) {
                    $_SESSION['RAPTOR_LANGUAGE_CODE'] = $code;
                    $link = $this->generateRouteLink('login') . "?forgot=$forgot_password";
                    \header("Location: $link", false, 302);
                    exit;
                }
            }
            $log_context += ['forgot' => $forgot];
            
            $now_date = new \DateTime();
            $then = new \DateTime($forgot['created_at']);
            $diff = $then->diff($now_date);
            if ($diff->y > 0 || $diff->m > 0 || $diff->d > 0
                || $diff->h > 0 || $diff->i > (int) CODESAUR_PASSWORD_RESET_MINUTES
            ) {
                throw new \Exception('Хугацаа дууссан код ашиглан нууц үг шинээр тааруулахыг хүсэв', StatusCodeInterface::STATUS_FORBIDDEN);
            }
            $vars += ['forgot_password' => $forgot_password, 'user_id' => $forgot['user_id']];
            
            $log_level = LogLevel::ALERT;
            $log_message = 'Нууц үгээ шинээр тааруулж эхэллээ.';
        } catch (\Throwable $e) {
            if ($e->getCode() == 404) {
                $notice = 'Хуурамч/устгагдсан/хэрэглэгдсэн мэдээлэл ашиглан нууц үг тааруулахыг оролдов';
            } else {
                $notice = $e->getMessage();
            }
            $vars += ['title' => $this->text('error'), 'notice' => $notice];

            $log_level = LogLevel::ERROR;
            $log_message = "Нууц үгээ шинээр тааруулж эхлэх үед алдаа гарч зогслоо. $notice";
            $log_context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $login_reset = $this->twigTemplate(\dirname(__FILE__) . '/login-reset-password.html', $vars);
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $login_reset->set($key, $value);
            }
            $login_reset->render();
            
            $log_context['auth_user'] = [];
            $this->indolog('dashboard', $log_level, $log_message, $log_context);
        }
    }
    
    public function setPassword()
    {
        try {
            $log_context = ['reason' => 'reset-password'];
            $parsedBody = $this->getParsedBody();
            $forgot_password = $parsedBody['forgot_password'];
            
            $vars = (array)$this->getAttribute('settings');
            $vars['forgot_password'] = $forgot_password;
            $log_context += ['payload' => $parsedBody];
            if (isset($parsedBody['password_new'])) {
                $password_new = $parsedBody['password_new'];
                unset($log_context['payload']['password_new']);
            } else {
                $password_new = null;
            }
            if (isset($parsedBody['password_retype'])) {
                $password_retype = $parsedBody['password_retype'];
                unset($log_context['payload']['password_retype']);
            } else {
                $password_retype = null;
            }
            $user_id = \filter_var($parsedBody['user_id'], \FILTER_VALIDATE_INT);
            if ($user_id === false) {
                throw new \Exception('<span class="text-secondary">Хэрэглэгчийн дугаар заагдаагүй байна.</span><br/>Мэдээлэл буруу оруулсан байна. Анхааралтай бөглөөд дахин оролдоно уу', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            $vars += ['user_id' => $user_id];

            if (empty($forgot_password) || empty($user_id)
                || !isset($password_new) || !isset($password_retype)
            ) {
                return $this->redirectTo('home');
            }
            
            if (empty($password_new) || $password_new != $password_retype) {
                throw new \Exception('<span class="text-secondary">Шинэ нууц үгээ буруу бичсэн.</span><br/>' . $this->text('password-must-match'), StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            $forgot = new ForgotModel($this->pdo);
            $record = $forgot->getRowBy(
                [
                    'status' => 1,
                    'forgot_password' => $forgot_password,
                    'user_id' => $user_id,
                    'remote_addr' => $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? ''
                ]
            );
            if (empty($record)) {
                throw new \Exception('Unauthorized!', StatusCodeInterface::STATUS_UNAUTHORIZED);
            }
            
            $users = new UsersModel($this->pdo);
            $user = $users->getById($user_id);
            if (empty($user)) {
                throw new \Exception('Invalid user', StatusCodeInterface::STATUS_NOT_FOUND);
            }
            unset($user['password']);

            $result = $users->updateById($user['id'], [
                'updated_by' => $user['id'],
                'updated_at' => \date('Y-m-d H:i:s'),
                'password' => \password_hash($password_new, \PASSWORD_BCRYPT)
            ]);
            if (empty($result)) {
                throw new \Exception("Can't reset user [{$user['username']}] password", StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            $updated = $forgot->updateById($record['id'], ['status' => 0]);
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $vars += ['title' => $this->text('success'), 'notice' => $this->text('set-new-password-success')];

            $log_level = LogLevel::INFO;
            $log_message = 'Нууц үг шинээр тохируулав';
            $log_context += ['auth_user' => $user];
        } catch (\Throwable $e) {
            $vars['error'] = $e->getMessage();
            
            $log_level = LogLevel::ERROR;
            $log_message = 'Шинээр нууц үг тааруулах үед алдаа гарлаа';
            $log_context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->twigTemplate(\dirname(__FILE__) . '/login-reset-password.html', $vars)->render();
            
            $this->indolog('dashboard', $log_level ?? LogLevel::NOTICE, $log_message ?? 'reset-password', $log_context);
        }
    }
    
    public function selectOrganization(int $id)
    {
        $home = $this->generateRouteLink('home');
        if (isset($this->getRequest()->getServerParams()['HTTP_REFERER'])) {
            $referer = $this->getRequest()->getServerParams()['HTTP_REFERER'];
            $location = \str_contains($referer, $home) ? $referer : $home;
        } else {
            $location = $home;
        }
        
        try {
            if (!$this->isUserAuthorized() || $id == 0) {
                throw new \Exception('Unauthorized', StatusCodeInterface::STATUS_UNAUTHORIZED);
            }

            $current_org_id = $this->getUser()->organization['id'];
            if ($id == $current_org_id) {
                throw new \Exception("Organization [$id] currently selected", StatusCodeInterface::STATUS_BAD_REQUEST);
            }

            $user_id = $this->getUserId();
            $payload = ['user_id' => $user_id, 'organization_id' => $id];
            $org_user_model = new OrganizationUserModel($this->pdo);
            if (!$this->isUser('system_coder')) {
                $org_user_belong = $org_user_model->retrieve($payload['organization_id'], $payload['user_id']);
                if (empty($org_user_belong)) {
                    throw new \Exception('User does not belong to an organization', StatusCodeInterface::STATUS_NOT_ACCEPTABLE);
                }
            }
            
            $JWT_AUTH = new JWTAuthMiddleware();
            $current_login = $JWT_AUTH->validate($_SESSION['RAPTOR_JWT']);
            $users = new UsersModel($this->pdo);
            $user = $users->getById($current_login['user_id']);
            if (!isset($user['id'])
                || $user['id'] != $payload['user_id']
            ) {
                throw new \Exception('Invalid user', StatusCodeInterface::STATUS_NOT_FOUND);
            } elseif ($user['status'] != 1) {
                throw new \Exception('Inactive user', StatusCodeInterface::STATUS_FORBIDDEN);
            }
            
            $org_model = new OrganizationModel($this->pdo);
            $organization = $org_model->getById($payload['organization_id']);
            if (!isset($organization['id'])) {
                throw new \Exception('Invalid organization', StatusCodeInterface::STATUS_FORBIDDEN);
            }
            
            $current_org_user = $org_user_model->retrieve($current_login['organization_id'], $current_login['user_id']);
            $org_user = $org_user_model->retrieve($payload['organization_id'], $payload['user_id']);
            if (!$org_user) {
                $rbac = new \Raptor\RBAC\RBAC($this->pdo, $payload['user_id']);
                if (!$rbac->hasRole('system_coder')) {
                    throw new \Exception('User does not belong to an organization', StatusCodeInterface::STATUS_NOT_ACCEPTABLE);
                }
                $org_user = $org_user_model->insert($payload + ['is_active' => 1, 'created_by' => $this->getUserId()]);
            }            
            $org_user_model->updateById($org_user['id'], ['status' => 1, 'updated_by' => $this->getUserId(), 'updated_at' => \date('Y-m-d H:i:s')]);
            $org_user_model->updateById($current_org_user['id'], ['status' => 0, 'updated_by' => $this->getUserId(), 'updated_at' => \date('Y-m-d H:i:s')]);
            
            $jwt = $JWT_AUTH->generate($payload);
            $_SESSION['RAPTOR_JWT'] = $jwt;
            
            $log_context = ['reason' => 'login-to-organization', 'enter' => $id, 'leave' => $current_org_id, 'jwt' => $jwt];
            $log_message = 'Хэрэглэгч {auth_user.first_name} {auth_user.last_name} нэвтэрсэн байгууллага сонгов';
            $this->indolog('dashboard', LogLevel::NOTICE, $log_message, $log_context);
        } catch (\Throwable $e) {
            $this->errorLog($e);
        } finally {
            \header("Location: $location", false, 302);
            
            exit;
        }
    }
    
    public function language(string $code)
    {
        $script_path = $this->getScriptPath();
        $home = (string) $this->getRequest()->getUri()->withPath($script_path);
        if (isset($this->getRequest()->getServerParams()['HTTP_REFERER'])) {
            $referer = $this->getRequest()->getServerParams()['HTTP_REFERER'];
            $location = \str_contains($referer, $home) ? $referer : $home;
        } else {
            $location = $home;
        }

        $from = $this->getLanguageCode();
        $language = $this->getLanguages();
        if (isset($language[$code]) && $code != $from) {
            $_SESSION['RAPTOR_LANGUAGE_CODE'] = $code;
            if ($this->isUserAuthorized()) {
                $user = $this->getUser()->profile;
                (new UsersModel($this->pdo))->updateById($user['id'], ['code' => $code]);

                $log_message = 'Хэрэглэгч {auth_user.first_name} {auth_user.last_name} системд ажиллах хэлийг {from}-с {code} болгон өөрчиллөө';
                $this->indolog('dashboard', LogLevel::NOTICE, $log_message, ['reason' => 'change-language', 'code' => $code, 'from' => $from]);
            }
        }
        
        \header("Location: $location", false, 302);
        exit;
    }
}
