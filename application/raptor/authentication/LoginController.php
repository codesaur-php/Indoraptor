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
        $rows = $reference->getRows(['WHERE' => "c.code='{$this->getLanguageCode()}' AND (p.keyword='tos' OR p.keyword='pp') AND p.is_active=1"]);
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
            $context = ['reason' => 'login'];
            $payload = $this->getParsedBody();
            
            $context += ['payload' => $payload];
            if (isset($context['payload']['password'])) {
                unset($context['payload']['password']);
            }

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
            unset($user['password']);
            
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

            $level = LogLevel::INFO;
            $message = "Хэрэглэгч {$user['first_name']} {$user['last_name']} системд нэвтрэв.";
            $this->respondJSON(['status' => 'success', 'message' => $message]);

            if (empty($user['code'])) {
                $users->updateById($user['id'], ['code' => $this->getLanguageCode()]);
            } elseif ($user['code'] != $this->getLanguageCode()
                && isset($this->getLanguages()[$user['code']])
            ) {
                $_SESSION['RAPTOR_LANGUAGE_CODE'] = $user['code'];
            }
        } catch (\Throwable $e) {
            if (isset($_SESSION['RAPTOR_JWT'])) {
                unset($_SESSION['RAPTOR_JWT']);
            }
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());

            $this->errorLog($e);
            
            $level = LogLevel::ERROR;
            $message = $e->getMessage();
            $context += ['reason' => 'attempt', 'error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->indolog('dashboard', $level, $message, $context, $user['id'] ?? null);
        }
    }

    public function logout()
    {
        if (isset($_SESSION['RAPTOR_JWT'])
            && $user = $this->getUser()?->profile
        ) {
            $message = "Хэрэглэгч {$user['first_name']} {$user['last_name']} системээс гарлаа.";
            $context = ['reason' => 'logout', 'jwt' => $_SESSION['RAPTOR_JWT']];
            
            unset($_SESSION['RAPTOR_JWT']);
            
            $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);
        }
        
        $this->redirectTo('home');
    }
    
    public function signup()
    {
        try {
            $context = ['reason' => 'request-new-user'];
            
            $payload = $this->getParsedBody();
            $context += ['payload' => $payload];
            if (isset($payload['password'])) {
                $password = $payload['password'];
                unset($context['payload']['password']);
            } else {
                $password = '';
            }
            if (isset($payload['password_re'])) {
                $passwordRe = $payload['password_re'];
                unset($context['payload']['password_re']);
            } else {
                $passwordRe = '';
            }
            if (empty($password) || $password != $passwordRe) {
                throw new \Exception($this->text('invalid-request'), StatusCodeInterface::STATUS_BAD_REQUEST);
            } else {
                unset($payload['password_re']);
            }

            $payload['code'] = $this->getLanguageCode();
            $payload['password'] = \password_hash($password, \PASSWORD_BCRYPT);
            
            $referenceModel = new ReferenceModel($this->pdo);
            $referenceModel->setTable('templates');
            $reference = $referenceModel->getRowBy(
                [
                    'c.code' => $this->getLanguageCode(),
                    'p.keyword' => 'request-new-user',
                    'p.is_active' => 1
                ]
            );
            if (empty($reference['localized'])) {
                throw new \Exception($this->text('email-template-not-set'), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            $content = $reference['localized'];
            
            if (empty($payload['email']) || empty($payload['username'])) {
                throw new \Exception('Invalid payload', StatusCodeInterface::STATUS_BAD_REQUEST);
            } elseif (\filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false) {
                throw new \Exception('Please provide valid email address.', StatusCodeInterface::STATUS_BAD_REQUEST);
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
            $template->source($content['content'][$profile['code']]);
            if ((new Mailer($this->pdo))
                    ->mail($profile['email'], null, $content['title'][$profile['code']], $template->output())
                    ->send()
            ) {
                $this->respondJSON(['status' => 'success', 'message' => $this->text('to-complete-registration-check-email')]);
            } else {
                $this->respondJSON(['status' => 'success', 'message' => 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг хүлээн авлаа!']);
            }
            
            $level = LogLevel::ALERT;
            $message = "{$profile['username']} нэртэй {$profile['email']} хаягтай шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүллээ";
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->respondJSON(['message' => '<span class="text-secondary">Шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүлэх үед алдаа гарч зогслоо.</span><br/>' . $message], $e->getCode());
            
            $level = LogLevel::ERROR;
            $context += ['error' => ['code' => $e->getCode(), 'message' => $message]];
        } finally {
            $this->indolog('dashboard', $level ?? LogLevel::NOTICE, $message ?? 'request-new-user', $context);
        }
    }
    
    public function forgot()
    {
        try {
            $context = ['reason' => 'login-forgot'];
            
            $payload = $this->getParsedBody();
            if (empty($payload['email'])
                || \filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false
            ) {
                throw new \Exception('Please provide valid email address', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            $referenceModel = new ReferenceModel($this->pdo);
            $referenceModel->setTable('templates');
            $reference = $referenceModel->getRowBy(
                [
                    'c.code' => $this->getLanguageCode(),
                    'p.keyword' => 'forgotten-password-reset',
                    'p.is_active' => 1
                ]
            );
            if (empty($reference['localized'])) {
                throw new \Exception($this->text('email-template-not-set'), StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            $content = $reference['localized'];
            
            if (empty($payload['code'])) {
                $payload['code'] = $this->getLanguageCode();
            }
            
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
                'status'      => 1,
                'use_id'      => \uniqid('use'),
                'email'       => $user['email'],
                'code'        => $payload['code'],
                'user_id'     => $user['id'],
                'username'    => $user['username'],
                'last_name'   => $user['last_name'],
                'first_name'  => $user['first_name'],
                'remote_addr' => $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? ''
            ]);
            if (!$request) {
                throw new \Exception("Хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт илгээснийг мэдээлллийн санд бүртгэн хадгалах үйлдэл гүйцэтгэх явцад алдаа гарч зогслоо.", StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
            }
            
            $level = LogLevel::INFO;
            $message = "{$payload['email']} хаягтай хэрэглэгч  нууц үгээ шинээр тааруулах хүсэлт илгээснийг бүртгэлээ";
            $context += ['forgot' => $request];

            $template = new MemoryTemplate();
            $template->set('email', $payload['email']);
            $template->set('minutes', CODESAUR_PASSWORD_RESET_MINUTES);
            $template->set('link', "{$this->generateRouteLink('login', [], true)}?forgot={$request['use_id']}");
            $template->source($content['content'][$payload['code']]);
            if ((new Mailer($this->pdo))
                    ->mail($payload['email'], null, $content['title'][$payload['code']], $template->output())
                    ->send()
            ) {
                $this->respondJSON(['status' => 'success', 'message' => $this->text('reset-email-sent')]);
            } else {
                $this->respondJSON(['status' => 'success', 'message' => $message]);
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->respondJSON(['message' => '<span class="text-secondary">Хэрэглэгч нууц үгээ шинэчлэх хүсэлт илгээх үед алдаа гарч зогслоо.</span><br/>' . $message], $e->getCode());

            $level = LogLevel::ERROR;
            $context += ['error' => ['code' => $e->getCode(), 'message' => $message]];
        } finally {
            $this->indolog('dashboard', $level ?? LogLevel::NOTICE, $message ?? 'login-forgot', $context);
        }
    }
    
    public function forgotPassword(string $use_id)
    {
        try {
            $context = ['reason' => 'forgot-password', 'use_id' => $use_id];
            
            $vars = (array)$this->getAttribute('settings');
            $vars['use_id'] = $use_id;
            
            $model = new ForgotModel($this->pdo);
            $forgot = $model->getRowBy([
                'use_id' => $use_id,
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
                    $link = $this->generateRouteLink('login') . "?forgot=$use_id";
                    \header("Location: $link", false, 302);
                    exit;
                }
            }
            $context += ['forgot' => $forgot];
            
            $now_date = new \DateTime();
            $then = new \DateTime($forgot['created_at']);
            $diff = $then->diff($now_date);
            if ($diff->y > 0 || $diff->m > 0 || $diff->d > 0
                || $diff->h > 0 || $diff->i > (int) CODESAUR_PASSWORD_RESET_MINUTES
            ) {
                throw new \Exception('Хугацаа дууссан код ашиглан нууц үг шинээр тааруулахыг хүсэв', StatusCodeInterface::STATUS_FORBIDDEN);
            }
            $vars += ['use_id' => $use_id, 'user_id' => $forgot['user_id']];
            
            $level = LogLevel::ALERT;
            $message = 'Нууц үгээ шинээр тааруулж эхэллээ.';
        } catch (\Throwable $e) {
            if ($e->getCode() == 404) {
                $notice = 'Хуурамч/устгагдсан/хэрэглэгдсэн мэдээлэл ашиглан нууц үг тааруулахыг оролдов';
            } else {
                $notice = $e->getMessage();
            }
            $vars += ['title' => $this->text('error'), 'notice' => $notice];

            $level = LogLevel::ERROR;
            $message = "Нууц үгээ шинээр тааруулж эхлэх үед алдаа гарч зогслоо. $notice";
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $login_reset = $this->twigTemplate(\dirname(__FILE__) . '/login-reset-password.html', $vars);
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $login_reset->set($key, $value);
            }
            $login_reset->render();
            
            $context += ['server_request' => [
                'code' => $this->getLanguageCode(),
                'method' => $this->getRequest()->getMethod(),
                'remote_addr' => $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? ''
            ]];
            
            $this->indolog('dashboard', $level, $message, $context, $forgot['user_id'] ?? null);
        }
    }
    
    public function setPassword()
    {
        try {
            $context = ['reason' => 'reset-password'];
            $parsedBody = $this->getParsedBody();
            $use_id = $parsedBody['use_id'];
            
            $vars = (array)$this->getAttribute('settings');
            $vars['use_id'] = $use_id;
            $context += ['payload' => $parsedBody, 'use_id' => $use_id];
            if (isset($parsedBody['password_new'])) {
                $password_new = $parsedBody['password_new'];
                unset($context['payload']['password_new']);
            } else {
                $password_new = null;
            }
            if (isset($parsedBody['password_retype'])) {
                $password_retype = $parsedBody['password_retype'];
                unset($context['payload']['password_retype']);
            } else {
                $password_retype = null;
            }
            $user_id = \filter_var($parsedBody['user_id'], \FILTER_VALIDATE_INT);
            if ($user_id === false) {
                throw new \Exception('<span class="text-secondary">Хэрэглэгчийн дугаар заагдаагүй байна.</span><br/>Мэдээлэл буруу оруулсан байна. Анхааралтай бөглөөд дахин оролдоно уу', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            $vars += ['user_id' => $user_id];

            if (empty($use_id) || empty($user_id)
                || !isset($password_new) || !isset($password_retype)
            ) {
                return $this->redirectTo('home');
            }
            $context += ['user_id' => $user_id];
            
            if (empty($password_new) || $password_new != $password_retype) {
                throw new \Exception('<span class="text-secondary">Шинэ нууц үгээ буруу бичсэн.</span><br/>' . $this->text('password-must-match'), StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            $forgot = new ForgotModel($this->pdo);
            $record = $forgot->getRowBy(
                [
                    'status' => 1,
                    'use_id' => $use_id,
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

            $level = LogLevel::INFO;
            $message = 'Нууц үг шинээр тохируулав';
            $context += ['user' => $user];
        } catch (\Throwable $e) {
            $vars['error'] = $e->getMessage();
            
            $level = LogLevel::ERROR;
            $message = 'Шинээр нууц үг тааруулах үед алдаа гарлаа';
            $context += ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
        } finally {
            $this->twigTemplate(\dirname(__FILE__) . '/login-reset-password.html', $vars)->render();
            
            $this->indolog('dashboard', $level ?? LogLevel::NOTICE, $message ?? 'reset-password', $context, $user['id'] ?? null);
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
            
            $context = ['reason' => 'login-to-organization', 'enter' => $id, 'leave' => $current_org_id, 'jwt' => $jwt];
            $message = "Хэрэглэгч {$user['first_name']} {$user['last_name']} нэвтэрсэн байгууллага сонгов.";
            $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);
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

                $message = "Хэрэглэгч {$user['first_name']} {$user['last_name']} системд ажиллах хэлийг $from-с $code болгон өөрчиллөө";
                $context = ['reason' => 'change-language', 'code' => $code, 'from' => $from];
                $this->indolog('dashboard', LogLevel::NOTICE, $message, $context);
            }
        }
        
        \header("Location: $location", false, 302);
        exit;
    }
}
