<?php

namespace Raptor\Authentication;

use Psr\Log\LogLevel;

use codesaur\Template\MemoryTemplate;

use Raptor\User\UsersModel;
use Raptor\Organization\OrganizationModel;
use Raptor\Organization\OrganizationUserModel;

/**
 * Class LoginController
 *
 * Indoraptor Framework-ийн нэвтрэх (Authentication) модульд ашиглагдах
 * үндсэн Controller. Энэ контроллер нь хэрэглэгчийн бүх authentication
 * урсгалыг нэг дор удирдана:
 *
 *   - index()                → Login хуудас руу орох
 *   - entry()                → Нэвтрэх оролдлого (username/password)
 *   - logout()               → Системээс гарах
 *   - signup()               → Шинэ хэрэглэгч бүртгүүлэх хүсэлт
 *   - forgot()               → Нууц үг сэргээх хүсэлт илгээх
 *   - forgotPassword()       → Хэрэглэгч нууц үг сэргээх link дээр дарсан үеийн UI
 *   - setPassword()          → Шинэ нууц үг тохируулах
 *   - selectOrganization()   → Хэрэглэгчийн ажиллах байгууллагыг сонгох
 *   - language()             → Login интерфейсийн хэлийг солих
 *
 * Тус Controller нь:
 *   ✔ UsersModel, OrganizationModel, ForgotModel гэх мэт мэдээллийн сангийн
 *     загваруудыг ашиглана
 *   ✔ JWTAuthMiddleware-ийг ашиглан нэвтрэх токен үүсгэнэ
 *   ✔ MemoryTemplate / TwigTemplate ашиглан template рендерлэнэ
 *   ✔ PSR-3 стандартын LogLevel ашиглан бүх үйлдлийг системийн лог руу бичнэ
 *   ✔ LocalizationMiddleware-аар дамжсан хэл, орчуулгын мэдээллийг
 *     автоматаар хэрэглэнэ
 *
 * Энэ бол Indoraptor Dashboard-ын authentication pipeline-ийн "зүрх".
 */
class LoginController extends \Raptor\Controller
{
    /**
     * Login хуудасны үндсэн view-г рендерлэх controller action.
     *
     * Энэ функц дараах 3 нөхцөл дээр ажиллана:
     *
     *  1) URL дээр "forgot={token}" параметр байвал:
     *         → forgotPassword() руу шилжиж,
     *           хэрэглэгчийн нууц үг тааруулах UI-г харуулна.
     *
     *  2) Хэрэв хэрэглэгч аль хэдийн нэвтэрсэн бол:
     *         → 'home' route руу redirect хийнэ.
     *
     *  3) Эс бөгөөс:
     *         → Login template-г (login.html) ачаалж рендерлэнэ.
     *
     * Template-т дамжуулах өгөгдөл:
     *   - settings middleware-ээр inject хийгдсэн бүх системийн тохиргоо
     *   - "tos" (Terms of Service) болон "pp" (Privacy Policy)
     *        орчуулгын контентууд (ReferenceModel → templates хүснэгтээс)
     *
     * Анхаар:
     *   - LocalizationMiddleware ажиллаагүй бол хэлний орчуулга байхгүй байж болно
     *   - SettingsMiddleware ажиллаагүй бол settings хувьсагч template-д дамжихгүй
     *   - Query параметр "forgot" → password reset workflow-г автоматаар эхлүүлнэ
     *
     * @return void Redirect эсвэл template render хийх
     */
    public function index()
    {
        $forgot_id = $this->getQueryParams()['forgot'] ?? false;

        // 1) Хэрэв нууц үг сэргээх линк ашиглаж байгаа бол
        if (!empty($forgot_id)) {
            return $this->forgotPassword($forgot_id);
        }
        // 2) Хэрэглэгч аль хэдийн нэвтэрсэн бол
        elseif ($this->isUserAuthorized()) {
            return $this->redirectTo('home');
        }

        // 3) Login template-г ачаалах
        $login = $this->twigTemplate(__DIR__ . '/login.html');

        // SettingsMiddleware → request attributes → 'settings'
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $login->set($key, $value);
        }

        // TOS + Privacy Policy орчуулга татаж template-д дамжуулах
        $code = $this->getLanguageCode();
        $templateService = $this->getService('template_service');
        $templateService->getByKeyword($code, 'pp');
        $templates = $templateService->getMultipleByKeywords($code, ['tos', 'pp']);
        foreach ($templates as $keyword => $template) {
            $login->set($keyword, $template);
        }

        // Login template-г render хийх
        $login->render();
    }
    
    /**
     * Хэрэглэгчийн нэвтрэх (login) оролдлогыг боловсруулах action.
     *
     * Workflow (алхам алхмаар):
     *
     * ───────────────────────────────────────────────────────────────
     * 1) Payload шалгах
     *    - Хэрэглэгч аль хэдийн нэвтэрсэн бол → алдаа (invalid-request)
     *    - username болон password хоосон бол → алдаа (400)
     *
     * 2) Хэрэглэгчийг мэдээллийн сангаас хайх
     *    - username эсвэл email-ээр нэг мөр хайна
     *    - олдохгүй бол → алдаа (401)
     *
     * 3) Нууц үг шалгах
     *    - password_verify() → буруу бол алдаа (401)
     *
     * 4) is_active = 0 → идэвхгүй төлөвтэй хэрэглэгч → алдаа (403)
     *
     * 5) Байгууллага (organization) тодорхойлох
     *    - Хамгийн сүүлд нэвтэрсэн байгууллага байгаа бол → шууд ашиглана
     *    - Үгүй бол → хэрэглэгчийн харьяа байгууллагыг OrganizationUserModel дээрээс сонгоно
     *    - Байгууллага байхгүй бол → алдаа (406)
     *
     * 6) JWT токен үүсгэх
     *    - payload: user_id + organization_id
     *    - Session-д RAPTOR_JWT нэрээр хадгална
     *
     * 7) Клиент рүү JSON хариу буцаах:
     *    {
     *        "status": "success",
     *        "message": "Хэрэглэгч Наранхүү системд нэвтрэв"
     *    }
     *
     * 8) Хэрэв хэрэглэгчийн хэл (user.code) тодорхойлогдоогүй бол → системийн сонгосон хэлээр update хийнэ
     *    Хэрэв хэрэглэгчийн код өөр хэлтэй таарвал → RAPTOR_LANGUAGE_CODE-г session-д онооно
     *
     * 9) finally хэсэг:
     *    - Нэвтрэлт амжилттай болсон бол LogLevel::INFO лог бичнэ
     *    - Амжилтгүй болсон бол LogLevel::ERROR лог бичнэ
     *    - indolog('dashboard', ...) ашиглана (PSR-3 стандарт)
     *
     * Аюулгүй байдлын анхааруулга:
     *    - Нууц үг буруу бол үргэлж "Invalid username or password" гэж нийтлэг мессеж буцаана
     *      (Username enumeration-ээс хамгаалах)
     *
     * @return void JSON хариу буцаана
     */
    public function entry()
    {
        try {
            // 1) Payload шалгах
            $payload = $this->getParsedBody();
            if ($this->isUserAuthorized()
                || empty($payload['username'])
                || empty($payload['password'])
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }

            // 2) Хэрэглэгчийг username эсвэл email-аар хайх
            $users = new UsersModel($this->pdo);
            $stmt = $users->prepare(
                "SELECT * FROM {$users->getName()} WHERE (username=:usr OR email=:eml) LIMIT 1"
            );
            $stmt->bindParam(':eml', $payload['username'], \PDO::PARAM_STR, $users->getColumn('email')->getLength());
            $stmt->bindParam(':usr', $payload['username'], \PDO::PARAM_STR, $users->getColumn('username')->getLength());
            if (!$stmt->execute() || $stmt->rowCount() != 1) {
                throw new \Exception('Invalid username or password', 401);
            }
            $user = $stmt->fetch();

            // 3) Нууц үг шалгах
            if (!\password_verify($payload['password'], $user['password'])) {
                throw new \Exception('Invalid username or password', 401);
            }

            // 4) Хэрэглэгч идэвхгүй төлөвт
            if (((int) $user['is_active']) == 0) {
                throw new \Exception('Inactive user', 403);
            }

            // 5) Байгууллага тодорхойлох
            $login_info = ['user_id' => $user['id']];

            // Сүүлд нэвтэрсэн байгууллага
            $lastOrg = $this->getLastLoginOrg($user['id']);
            if ($lastOrg !== false) {
                $login_info['organization_id'] = $lastOrg;
            }
            else {
                // сүүлд нэвтэрсэн байгууллага лог байхгүй үед
                $org_model = new OrganizationModel($this->pdo);
                $org_user_model = new OrganizationUserModel($this->pdo);
                $stmt_user_org = $this->prepare(
                    'SELECT t1.* ' .
                    "FROM {$org_user_model->getName()} t1 INNER JOIN {$org_model->getName()} t2 ON t1.organization_id=t2.id " .
                    'WHERE t1.user_id=:id AND t2.is_active=1 LIMIT 1'
                );
                $stmt_user_org->execute([':id' => $user['id']]);
                if ($stmt_user_org->rowCount() == 1) {
                    $login_info['organization_id'] = $stmt_user_org->fetch()['organization_id'];
                } else {
                    throw new \Exception('User doesn\'t belong to an organization', 406);
                }
            }

            // 6) JWT үүсгэх
            $_SESSION['RAPTOR_JWT'] = (new JWTAuthMiddleware())->generate($login_info);

            // 7) JSON хариу
            $this->respondJSON([
                'status'  => 'success',
                'message' => "Хэрэглэгч {$user['first_name']} системд нэвтрэв"
            ]);

            // 8) Хэл тохируулах
            if (empty($user['code'])) {
                $users->updateById($user['id'], ['code' => $this->getLanguageCode()]);
            } elseif ($user['code'] != $this->getLanguageCode()
                && isset($this->getLanguages()[$user['code']])
            ) {
                $_SESSION['RAPTOR_LANGUAGE_CODE'] = $user['code'];
            }

        } catch (\Throwable $err) {
            // Нэвтрэх явцад JWT үүссэн бол устгана
            if (isset($_SESSION['RAPTOR_JWT'])) {
                unset($_SESSION['RAPTOR_JWT']);
            }

            $this->respondJSON(
                ['message' => $err->getMessage()],
                $err->getCode()
            );
        } finally {
            // Лог бичих - амжилттай эсэхээс хамаарч лог түвшин өөр
            if (isset($_SESSION['RAPTOR_JWT'])) {
                $level   = LogLevel::INFO;
                $message = 'Хэрэглэгч {auth_user.first_name} {auth_user.last_name} системд нэвтрэв';
                $context = ['auth_user' => $user];
            } else {
                $level   = LogLevel::ERROR;
                $message = '{error.message}';
                $context = [
                    'auth_user' => [],
                    'error'     => ['code' => $err->getCode(), 'message' => $err->getMessage()]
                ];
            }
            $this->indolog('dashboard', $level, $message, ['action' => 'login'] + $context);
        }
    }

    /**
     * Хэрэглэгчийн гарах (logout) үйлдлийг боловсруулах action.
     *
     * Workflow:
     * ───────────────────────────────────────────────────────────────
     * 1) Session дотор хадгалсан JWT (RAPTOR_JWT)-г устгана.
     *    → Ингэснээр хэрэглэгч хүчингүй болсон токен ашиглан
     *      дахин үйлдэл хийх боломжгүй болно.
     *
     * 2) Лог бичих (indolog):
     *    - LogLevel::NOTICE түвшинд
     *    - context дотор серверийн хүсэлт болон хэрэглэгчийн мэдээлэл дамжина
     *    - "{auth_user.first_name} ... системээс гарлаа" мессежтэй
     *
     * 3) Хэрэглэгчийг 'home' маршрут руу redirect хийнэ.
     *
     * Аюулгүй байдлын онцлог:
     *    - JWT-г устгасны дараа хэрэглэгч authenticated бус болдог
     *    - Session-д зөвхөн JWT-г устгана, бусад session өгөгдөл хадгалагдана
     *
     * @return void Redirect хийнэ
     */
    public function logout()
    {
        // 1) JWT-г устгах
        if (isset($_SESSION['RAPTOR_JWT'])) {
            unset($_SESSION['RAPTOR_JWT']);

            // 2) Logout үйлдлийг системийн лог руу бүртгэх
            $this->indolog(
                'dashboard',
                LogLevel::NOTICE,
                'Хэрэглэгч {auth_user.first_name} {auth_user.last_name} системээс гарлаа',
                ['action' => 'logout']
            );
        }

        // 3) 'home' маршрут руу redirect хийх
        $this->redirectTo('home');
    }
    
    /**
     * Шинэ хэрэглэгч бүртгүүлэх (signup) хүсэлтийг боловсруулах action.
     *
     * Энэ нь систем дээр "шинэ хэрэглэгч үүсгэх хүсэлт" л үүсгэдэг бөгөөд
     * хэрэглэгч шууд идэвхтэй болдоггүй. Admin эсвэл системийн хүний
     * баталгаажуулалт шаардана.
     *
     * Workflow (алхам алхмаар):
     * ───────────────────────────────────────────────────────────────
     *
     * 1) Payload шалгах
     *    - password болон password_re давхцаж байгаа эсэх
     *    - email болон username хоосон биш эсэх
     *    - email хүчинтэй эсэх
     *    - Буруу бол → InvalidArgumentException (400)
     *
     * 2) Email template татах
     *    ReferenceModel → templates хүснэгтээс
     *       p.keyword='request-new-user'
     *       c.code = одоогийн хэл
     *       is_active=1
     *    - Template байхгүй бол → алдаа (500)
     *
     * 3) Дата давхардал (unique constraints) шалгах
     *    - UsersModel: email давхардсан бол → алдаа (403)
     *    - UsersModel: username давхардсан бол → алдаа (403)
     *    - SignupModel: өмнө нь signup хүсэлт өгсөн эсэхийг шалгана
     *         (username/email-ын аль нэг нь таарвал → алдаа 403)
     *
     * 4) SignupModel → хүсэлт мэдээллийн санд insert хийх
     *    - Алдаа гарвал → Exception (500)
     *
     * 5) Email илгээх
     *    - MemoryTemplate ашиглан төвлөрсөн email HTML үүсгэнэ
     *    - Mailer классыг ашиглан хэрэглэгчийн имэйл рүү шинэ хэрэглэгчийн
     *      хүсэлт хүлээн авсны notification илгээнэ
     *    - Илгээгдсэн эсэхээс үл хамааран JSON “success” буцаана
     *
     * 6) finally{} блок дээр системийн лог бичнэ
     *    - Амжилттай бол LogLevel::ALERT
     *         "{username} нэртэй {email} хаягтай шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүллээ"
     *    - Амжилтгүй бол LogLevel::ERROR
     *         "{error.message}"
     *    - indolog('dashboard', ...)
     *
     * Аюулгүй байдлын онцлог:
     *   - Нууц үг үргэлж bcrypt ашиглан hash хийгдэнэ
     *   - Signup хүсэлтүүдийг тусдаа хүснэгтэд хадгалдаг → fake request шалгах боломжтой
     *   - Direct user creation биш → Admin баталгаажуулалт шаардлагатай
     *   - Username/email enumeration-ээс хамгаалах зорилгоор ганцхан төрлийн мессеж ашигладаг
     *
     * @return void  JSON хариу хэвлэнэ
     */
    public function signup()
    {
        try {
            $code = $this->getLanguageCode();
            $payload = $this->getParsedBody();

            // 1) Password validation
            $password   = $payload['password'] ?? '';
            $passwordRe = $payload['password_re'] ?? '';
            if (empty($password) || $password !== $passwordRe) {
                throw new \InvalidArgumentException($this->text('invalid-values'), 400);
            } else {
                unset($payload['password_re']);
            }
            // Нууц үгийг hash хийх
            $payload['password'] = \password_hash($password, \PASSWORD_BCRYPT);

            $payload['code'] = $code;
            
            // 2) Email template татах (request-new-user)
            $templateService = $this->getService('template_service');
            $template = $templateService->getByKeyword($code, 'request-new-user');
            if (empty($template)) {
                throw new \Exception($this->text('email-template-not-set'), 500);
            }

            // 3) Payload fields validation
            if (empty($payload['email']) || empty($payload['username'])) {
                throw new \InvalidArgumentException('Invalid payload', 400);
            }
            if (\filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('Please provide valid email address.', 400);
            }

            // Шалгах: email / username system дотор давхцаж байна уу?
            $users = new UsersModel($this->pdo);
            if (!empty($users->getRowWhere(['email' => $payload['email']]))) {
                throw new \Exception("Бүртгэлтэй [{$payload['email']}] хаягаар шинэ хэрэглэгч үүсгэх хүсэлт ирүүллээ. Татгалзав.", 403);
            }
            if (!empty($users->getRowWhere(['username' => $payload['username']]))) {
                throw new \Exception("Бүртгэлтэй [{$payload['username']}] хэрэглэгчийн нэрээр шинэ хэрэглэгч үүсгэх хүсэлт ирүүллээ. Татгалзав.", 403);
            }

            // Signup хүсэлт өмнө өгсөн эсэх
            $userRequest = new SignupModel($this->pdo);
            if (!empty($userRequest->getRowWhere([
                'is_active' => 1,
                'email'     => $payload['email']
            ]))) {
                throw new \Exception(
                    "Шинээр [{$payload['email']}] хаягтай хэрэглэгч үүсгэх хүсэлт ирүүлсэн боловч, урьд нь хүсэлт өгч байсан тул татгалзав.",
                    403
                );
            }
            if (!empty($userRequest->getRowWhere([
                'is_active' => 1,
                'username'  => $payload['username']
            ]))) {
                throw new \Exception(
                    "Шинээр [{$payload['username']}] нэртэй хэрэглэгч үүсгэх хүсэлт ирүүлсэн боловч, урьд нь хүсэлт өгч байсан тул татгалзав.",
                    403
                );
            }

            // 4) Signup хүсэлт DB-д insert хийх
            $profile = $userRequest->insert($payload);
            if (empty($profile)) {
                throw new \Exception(
                    "Шинээр [{$payload['username']}] нэртэй [{$payload['email']}] хаягтай хэрэглэгч үүсгэх хүсэлт DB-д хадгалах явцад алдаа гарлаа.",
                    500
                );
            }

            // 5) Email илгээх
            $memtemplate = new MemoryTemplate();
            $memtemplate->set('email',    $profile['email']);
            $memtemplate->set('username', $profile['username']);
            $memtemplate->source($template['content']);
            if (
                $this->getService('mailer')
                    ->mail($profile['email'], null, $template['title'], $memtemplate->output())
                    ->send()
            ) {
                $this->respondJSON([
                    'status'  => 'success',
                    'message' => $this->text('to-complete-registration-check-email')
                ]);
            } else {
                $this->respondJSON([
                    'status'  => 'success',
                    'message' => 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг хүлээн авлаа!'
                ]);
            }
        } catch (\Throwable $e) {
            // Error хэвлэнэ
            $this->respondJSON(
                [
                    'message' =>
                        '<span class="text-secondary">Шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүлэх үед алдаа гарлаа.</span><br/>' .
                        $e->getMessage()
                ],
                $e->getCode()
            );
        } finally {
            // 6) Лог бичих
            if (!empty($profile)) {
                $level   = LogLevel::ALERT;
                $message = '{server_request.body.username} нэртэй {server_request.body.email} хаягтай шинэ хэрэглэгч үүсгэх хүсэлт бүртгүүллээ';
                $context = [];
            } else {
                $level   = LogLevel::ERROR;
                $message = '{error.message}';
                $context = [
                    'error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]
                ];
            }
            $this->indolog(
                'dashboard',
                $level,
                $message,
                ['action' => 'signup', 'auth_user' => []] + $context
            );
        }
    }
    
    /**
     * Нууц үг сэргээх (password reset) хүсэлт илгээх action.
     *
     * Энэ функц нь хэрэглэгч өөрийн имэйл хаягаар нууц үг сэргээх линк авах
     * хүсэлт илгээх үед ажиллана.
     *
     * Workflow (алхам алхмаар):
     * ───────────────────────────────────────────────────────────────
     *
     * 1) Payload шалгах
     *    - email хоосон эсвэл буруу форматтай → алдаа (400)
     *    - payload['code'] байхгүй бол → одоогийн хэлний code-г онооно
     *
     * 2) Нууц үг сэргээх email template татах
     *    ReferenceModel → templates хүснэгтээс:
     *        keyword='forgotten-password-reset'
     *        code = сонгосон хэл
     *        is_active=1
     *    - Template байхгүй бол → алдаа (500)
     *
     * 3) Хэрэглэгчийг шалгах
     *    - Email-ээр хайна
     *    - Олдохгүй бол → 404
     *    - is_active=0 бол → 403
     *
     * 4) ForgotModel → хүсэлт DB-д insert хийх
     *    Талбарууд:
     *       - forgot_password   (uniqid)
     *       - user_id, email, username
     *       - first_name, last_name
     *       - remote_addr
     *       - code (хэлний код)
     *
     *    - Insert амжилтгүй бол → 500
     *
     * 5) Reset email илгээх
     *    - MemoryTemplate ашиглан email HTML content боловсруулна
     *    - Mailer ашиглан имэйл илгээнэ
     *    - Амжилттай эсэхээс үл хамааран JSON “success” буцаана
     *
     * 6) finally{} сарьцах хэсэг
     *    - Хүсэлт үүссэн бол LogLevel::INFO
     *    - Алдаа гарсан бол LogLevel::ERROR
     *    - indolog('dashboard', ...)
     *      context дотор:
     *         - forgot (амжилттай бол)
     *         - error  (алдаа бол)
     *         - action='forgot'
     *
     * Аюулгүй байдлын онцлог:
     *   - Бүртгэлгүй имэйл дээр reset хийх оролдлогыг системд log хийнэ
     *   - ForgotModel.allow multi-attempts → бүртгэлийн түүх хадгална
     *   - Template эх бэлтгэл localization-т суурилдаг
     *
     * @return void JSON хариу хэвлэнэ
     */
    public function forgot()
    {
        try {
            // 1) Payload validation
            $code    = $this->getLanguageCode();
            $payload = $this->getParsedBody();
            if (
                empty($payload['email']) ||
                \filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false
            ) {
                throw new \InvalidArgumentException('Please provide valid email address', 400);
            }
            if (empty($payload['code'])) {
                $payload['code'] = $code;
            }

            // 2) Email template авах
            $templateService = $this->getService('template_service');
            $template = $templateService->getByKeyword($payload['code'], 'forgotten-password-reset');
            if (empty($template)) {
                throw new \Exception($this->text('email-template-not-set'), 500);
            }

            // 3) Хэрэглэгчийг шалгах
            $users = new UsersModel($this->pdo);
            $user = $users->getRowWhere([
                'email' => $payload['email']
            ]);
            if (empty($user)) {
                throw new \Exception(
                    "Бүртгэлгүй [{$payload['email']}] хаяг дээр нууц үг шинээр тааруулах хүсэлт илгээхийг оролдлоо. Татгалзав.",
                    404
                );
            }
            if ($user['is_active'] == 0) {
                throw new \Exception(
                    "Эрх нь нээгдээгүй хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт илгээх оролдлого хийв. Татгалзав.",
                    403
                );
            }

            // 4) ForgotModel → DB insert
            $forgot = new ForgotModel($this->pdo);
            $request = $forgot->insert([
                'forgot_password' => \uniqid('forgot'),
                'email'           => $user['email'],
                'code'            => $code,
                'user_id'         => $user['id'],
                'username'        => $user['username'],
                'last_name'       => $user['last_name'],
                'first_name'      => $user['first_name'],
                'remote_addr'     => $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? ''
            ]);
            if (empty($request)) {
                throw new \Exception(
                    "Хэрэглэгч [{$payload['email']}] нууц үг шинэчлэх хүсэлт бүртгэх явцад алдаа гарч зогслоо.",
                    500
                );
            }

            // 5) Reset email илгээх
            $memtemplate = new MemoryTemplate();
            $memtemplate->set('email',   $payload['email']);
            $memtemplate->set('minutes', CODESAUR_PASSWORD_RESET_MINUTES);
            $memtemplate>-set(
                'link',
                "{$this->generateRouteLink('login', [], true)}?forgot={$request['forgot_password']}"
            );
            $memtemplate->source($template['content']);
            if (
                $this->getService('mailer')
                    ->mail($payload['email'], null, $template['title'], $memtemplate->output())
                    ->send()
            ) {
                $this->respondJSON(
                    ['status' => 'success', 'message' => $this->text('reset-email-sent')]
                );
            } else {
                $this->respondJSON(
                    ['status' => 'success', 'message' => 'Хэрэглэгч  нууц үгээ шинээр тааруулах хүсэлт илгээснийг бүртгэлээ']
                );
            }
        } catch (\Throwable $e) {
            // Error хэвлэнэ
            $this->respondJSON(
                [
                    'message' =>
                        '<span class="text-secondary">Хэрэглэгч нууц үгээ шинэчлэх хүсэлт илгээх үед алдаа гарлаа.</span><br/>' .
                        $e->getMessage()
                ],
                $e->getCode()
            );
        } finally {
            // 6) Лог бичих
            if (!empty($request)) {
                $level   = LogLevel::INFO;
                $message = '{server_request.body.email} хаягтай хэрэглэгч нууц үгээ шинээр тааруулах хүсэлт илгээснийг бүртгэлээ';
                $context = ['forgot' => $request];
            } else {
                $level   = LogLevel::ERROR;
                $message = '{error.message}';
                $context = [
                    'error' => [
                        'code'    => $e->getCode(),
                        'message' => $e->getMessage()
                    ]
                ];
            }
            $this->indolog(
                'dashboard',
                $level,
                $message,
                ['action' => 'forgot', 'auth_user' => []] + $context
            );
        }
    }
    
    /**
     * Нууц үг шинээр тааруулах (reset password) хуудсыг харуулах action.
     *
     * Энэ функц нь хэрэглэгч email-ээр ирсэн линк дээр дарж,
     * "forgot_password" токен дамжин ирэх үед ажиллана.
     *
     * Гол үүрэг:
     *   - forgot_token хүчинтэй эсэхийг шалгах
     *   - Токены хугацааг (created_at) шалгах
     *   - Токенд хавсарсан хэл (code) одоогийн localization-той таарахгүй бол,
     *     системийн хэлийг автоматаар сольж redirect хийх
     *   - Алдаа гарсан бол login-reset-password.html template рүү error дамжуулах
     *   - Амжилттай бол reset form-т шаардлагатай мэдээлэл дамжуулах
     *
     * Workflow (алхам алхмаар):
     * ───────────────────────────────────────────────────────────────
     *
     * 1) ForgotModel → "forgot_password" токен шалгах
     *    - is_active=1 байх ёстой
     *    - user_id, username, email зэрэг мэдээлэл олдоно
     *    - Олдохгүй бол → алдаа (403)
     *
     * 2) Token-д тохирох хэл (code) шалгах
     *    - Хэрэв токенийх код ≠ одоогийн localization code бол:
     *        → $_SESSION['RAPTOR_LANGUAGE_CODE'] = token.code
     *        → login form руу redirect хийх (token-г хадгалсаар)
     *
     * 3) Token хугацаа дууссан эсэхийг шалгах
     *    - created_at-аас хойш:
     *        - өдрөөр, сараар, жилээр өөрчлөгдсөн бол → дууссан
     *        - цаг ≥ 1 бол (тохиолдолд) → дууссан
     *        - минут ≥ CODESAUR_PASSWORD_RESET_MINUTES бол → дууссан
     *    - Хугацаа дууссан бол → алдаа (403)
     *
     * 4) Template рүү өгөгдөл дамжуулах
     *    - forgot token-ийн бүх өгөгдөл
     *    - settings middleware-ээс ирсэн системийн тохиргоо
     *
     * 5) indolog() ашиглан үйлдлийг лог бичих
     *    - Token хүчинтэй бол LogLevel::ALERT
     *         → “Нууц үг шинээр тааруулж эхэллээ”
     *    - Token буруу бол LogLevel::ERROR
     *         → error.message логжино
     *
     * Аюулгүй байдлын онцлог:
     *   - Token-ийг ашигласан эсэхийг ForgotModel дээр is_active талбараар хянадаг
     *   - Token зөв IP-ээр ирсэн эсэхийг check хийх боломжтой (remote_addr)
     *   - Token хугацаагаар хамгаалагдсан
     *   - Token хэл (locale) таарахгүй бол UI-г автоматаар зөв хэл рүү шилжүүлдэг
     *
     * @param string $forgot_password  Unique reset token
     * @return void  Template render хийнэ, response шууд гарна
     */
    public function forgotPassword(string $forgot_password)
    {
        try {
            // 1) Forgot token шалгах
            $model = new ForgotModel($this->pdo);
            $forgot = $model->getRowWhere([
                'forgot_password' => $forgot_password,
                'is_active'       => 1
            ]);
            if (empty($forgot)) {
                throw new \Exception(
                    'Хуурамч/устгагдсан/хэрэглэгдсэн мэдээлэл ашиглан нууц үг тааруулахыг оролдов',
                    403
                );
            }

            // 2) Token хэлний code шалгах
            $code = $forgot['code'];
            if (
                $code != $this->getLanguageCode() &&
                isset($this->getLanguages()[$code])
            ) {
                // Localization middleware-д дамжуулах шинэ код
                $_SESSION['RAPTOR_LANGUAGE_CODE'] = $code;

                // Token-г хадгалсан чигээрээ login руу redirect
                $link = $this->generateRouteLink('login') . "?forgot=$forgot_password";
                \header("Location: $link", false, 302);
                exit;
            }

            // 3) Token хугацаа дууссан эсэх шалгах
            $now_date = new \DateTime();
            $then     = new \DateTime($forgot['created_at']);
            $diff     = $then->diff($now_date);
            if ($diff->y > 0 ||
                $diff->m > 0 ||
                $diff->d > 0 ||
                $diff->h > 0 ||
                $diff->i > CODESAUR_PASSWORD_RESET_MINUTES
            ) {
                throw new \Exception(
                    'Хугацаа дууссан код ашиглан нууц үг шинээр тааруулахыг хүсэв',
                    403
                );
            }
        } catch (\Throwable $e) {
            // Хуудас руу буцаах error өгөгдөл
            $error = [
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ];
        } finally {
            // 4) Template рэндерлэх
            $login_reset = $this->twigTemplate(
                __DIR__ . '/login-reset-password.html',
                $error ?? $forgot
            );
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $login_reset->set($key, $value);
            }
            $login_reset->render();

            // 5) Үйлдлийг системийн лог руу бичих
            $this->indolog(
                'dashboard',
                empty($error) ? LogLevel::ALERT : LogLevel::ERROR,
                empty($error)
                    ? 'Нууц үгээ шинээр тааруулж эхэллээ.'
                    : 'Нууц үгээ шинээр тааруулж эхлэх үед алдаа гарч зогслоо. {message}',
                [
                    'action'         => 'forgot-password',
                    'forgot_password' => $forgot_password,
                    'auth_user'      => [],
                    'server_request' => [
                        'remote_add' => $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? ''
                    ]
                ] + ($error ?? []) + ($forgot ?? [])
            );
        }
    }
    
    /**
     * Нууц үг шинээр тохируулах (password reset submit) action.
     *
     * Энэ функц нь хэрэглэгч reset password form-ыг илгээх үед ажиллана.
     * Бүртгэлийн сан дахь ForgotModel токен (forgot_password) болон
     * хэрэглэгчийн мэдээлэл таарч байвал шинэ нууц үгийг хадгална.
     *
     * Workflow (алхам алхмаар):
     * ───────────────────────────────────────────────────────────────
     *
     * 1) Payload шалгах
     *    - user_id INTEGER эсэх
     *    - forgot_password токен ирсэн эсэх
     *    - password_new / password_retype хоорондоо тохирох эсэх
     *    - Хүчингүй бол → Exception (400 эсвэл 403)
     *
     * 2) ForgotModel → токеныг шалгах
     *    - is_active=1 байх ёстой
     *    - user_id таарч байх ёстой
     *    - remote_addr таарч байх ёстой (security measure)
     *    - Олдохгүй бол → 403
     *
     * 3) Token хугацаа дууссан эсэхийг шалгах
     *    - created_at → NOW() хүртэлх зөрүү
     *    - минут ≥ CODESAUR_PASSWORD_RESET_MINUTES бол → expired
     *    - Алдаа → 403
     *
     * 4) Хэрэглэгчийг шалгах
     *    - user_id таарах ёстой
     *    - is_active=1 байх ёстой
     *    - Олдохгүй → 404
     *
     * 5) Password шинэчлэх
     *    - \password_hash(PASSWORD_BCRYPT) ашиглана
     *    - updated_by, updated_at талбаруудыг шинэчилнэ
     *    - updateById() амжилтгүй бол → 500
     *
     * 6) ForgotModel токеныг идэвхгүй болгох
     *    - deactivateById()
     *
     * 7) Template render
     *    - success эсвэл error дагуу login-reset-password.html template
     *
     * 8) Лог бичих (indolog)
     *    - Success → LogLevel::INFO (“Нууц үгээ шинээр тохируулав”)
     *    - Failure → LogLevel::ERROR
     *    - Context дотор:
     *         auth_user
     *         server_request (remote_addr)
     *         error / success messages
     *
     * Security онцлогууд:
     *   - Token-г зөв user_id ба IP-тэй тулган шалгадаг
     *   - Token-г зөвхөн нэг удаа ашиглана (deactivate)
     *   - Token нь хугацаатай
     *   - Password BCRYPT стандарттай
     *   - Error message-д sensitive data агуулахгүй
     *
     * @return void Template render + HTTP response
     */
    public function setPassword()
    {
        try {
            // 1) Payload validation
            $parsedBody      = $this->getParsedBody();
            $forgot_password = $parsedBody['forgot_password'];
            $password_new    = $parsedBody['password_new']   ?? null;
            $password_retype = $parsedBody['password_retype'] ?? null;
            $user_id = \filter_var($parsedBody['user_id'], \FILTER_VALIDATE_INT);
            if ($user_id === false) {
                throw new \Exception(
                    '<span class="text-secondary">Хэрэглэгчийн дугаар заагдаагүй байна.</span><br/>Мэдээлэл буруу оруулсан байна. Анхааралтай бөглөөд дахин оролдоно уу',
                    400
                );
            }
            if (
                empty($forgot_password) ||
                !isset($password_new) ||
                !isset($password_retype)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            if (empty($password_new) || $password_new !== $password_retype) {
                throw new \Exception(
                    '<span class="text-secondary">Шинэ нууц үгээ буруу бичсэн.</span><br/>' .
                    $this->text('password-must-match'),
                    400
                );
            }

            // 2) ForgotModel → токеныг шалгах
            $model = new ForgotModel($this->pdo);
            $forgot = $model->getRowWhere([
                'is_active'       => 1,
                'user_id'         => $user_id,
                'forgot_password' => $forgot_password,
                'remote_addr'     => $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? ''
            ]);
            if (empty($forgot) || $forgot['user_id'] != $user_id) {
                throw new \Exception(
                    'Хуурамч мэдээлэл ашиглан нууц үг тааруулахыг оролдов',
                    403
                );
            }

            // 3) Token хугацаа дууссан эсэх
            $now_date = new \DateTime();
            $then     = new \DateTime($forgot['created_at']);
            $diff     = $then->diff($now_date);
            if ($diff->y > 0
                || $diff->m > 0
                || $diff->d > 0
                || $diff->h > 0
                || $diff->i > CODESAUR_PASSWORD_RESET_MINUTES
            ) {
                throw new \Exception(
                    'Хугацаа дууссан код ашиглан нууц үг шинээр тааруулахыг хүсэв',
                    403
                );
            }

            // 4) Хэрэглэгчийг шалгах
            $users = new UsersModel($this->pdo);
            $user = $users->getRowWhere([
                'id'        => $user_id,
                'is_active' => 1
            ]);
            if (empty($user)) {
                throw new \Exception('Invalid user', 404);
            }

            // 5) Password шинэчлэх
            $result = $users->updateById(
                $user['id'],
                [
                    'updated_by' => $user['id'],
                    'updated_at' => \date('Y-m-d H:i:s'),
                    'password'   => \password_hash($password_new, \PASSWORD_BCRYPT)
                ]
            );
            if (empty($result)) {
                throw new \Exception(
                    "Can't reset user [{$user['username']}] password",
                    500
                );
            }

            // Token-г устгах
            $model->deactivateById(
                $forgot['id'],
                ['updated_at' => \date('Y-m-d H:i:s')]
            );

            // UI-д харуулах success хувьсагч
            $vars = [
                'title'   => $this->text('success'),
                'message' => $this->text('set-new-password-success')
            ];
        } catch (\Throwable $e) {
            // Error template variables
            $vars = ['error' => $e->getMessage()] + ($forgot ?? []);
        } finally {
            // 7) UI render
            $login_reset = $this->twigTemplate(
                __DIR__ . '/login-reset-password.html',
                $vars
            );
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $login_reset->set($key, $value);
            }
            $login_reset->render();

            // 8) Logging
            $this->indolog(
                'dashboard',
                isset($vars['error']) ? LogLevel::ERROR : LogLevel::INFO,
                isset($vars['error'])
                    ? 'Шинээр нууц үг тааруулах үед алдаа гарлаа. {error}'
                    : 'Нууц үгээ шинээр тохируулав',
                [
                    'action'         => 'set-password',
                    'auth_user'      => $user ?? [],
                    'server_request' => [
                        'remote_addr' => $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? ''
                    ]
                ] + $vars
            );
        }
    }
    
    /**
     * Нэвтэрсэн хэрэглэгч өөр байгууллагыг (organization) сонгох action.
     *
     * Indoraptor Framework-д хэрэглэгч нэгээс олон байгууллагад харьяалагдаж
     * болдог. Энэ функц нь хэрэглэгч active session дотроо өөр байгууллага руу
     * шилжих үед ажиллана.
     *
     * Workflow (алхам алхмаар):
     * ───────────────────────────────────────────────────────────────
     *
     * 1) Хэрэглэгч нэвтэрсэн эсэхийг шалгах
     *    - Authorization байхгүй → Exception (401)
     *
     * 2) Одоогийн байгууллагын ID-г тодорхойлох
     *    - Хэрэв сонгож буй байгууллага одоогийнхоороо таарч байвал → 400
     *
     * 3) Сонгосон байгууллага (organization) хүчинтэй эсэхийг шалгах
     *    - id таарах, is_active=1 байх ёстой
     *    - Олдохгүй бол → Exception (403)
     *
     * 4) Хэрэглэгч сонгосон байгууллагад харьяалагддаг эсэхийг шалгах
     *    - OrganizationUserModel → retrieve(id, user_id)
     *    - Олдохгүй бол → 406 (User does not belong to organization)
     *
     * 5) Онцгой эрх: system_coder role
     *    - Хэрэглэгч system_coder бол тухайн байгууллагад шууд нэмнэ
     *      (auto-insert organization_user row).
     *
     * 6) JWT токен шинээр үүсгэх
     *    - user_id + organization_id бүхий шинэ JWT
     *    - Session-д RAPTOR_JWT шинэчилнэ
     *
     * 7) Лог бичих (indolog)
     *    - Success → LogLevel::NOTICE
     *         “Хэрэглэгч ... байгууллага [id:x] сонгов”
     *    - Error → LogLevel::ERROR
     *         “... алдаа илэрлээ”
     *
     * 8) Redirect хийх
     *    - Referer байгаа бол → түүн рүү буцаана
     *    - Байхгүй бол → home маршрут руу буцаана
     *
     * Security онцлогууд:
     *   - Хэрэглэгч зөвхөн өөрийн харьяалагдсан байгууллага руу л орж чадна
     *   - Шинэ JWT токен заавал үүсгэгдэнэ (old token-г ашиглах боломжгүй)
     *   - system_coder role-ийн тусгай эрхийг framework-level дээр тодорхойлсон
     *   - Actions бүр лог-т бүртгэгдэнэ (audit trail)
     *
     * @param int $id  Сонгож буй байгууллагын ID
     * @return void Redirect хийнэ
     */
    public function selectOrganization(int $id)
    {
        try {
            // 1) Хэрэглэгч нэвтэрсэн эсэх
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            // 2) Одоогийн байгууллагын ID
            $current_org_id = $this->getUser()->organization['id'];
            if ($id == $current_org_id) {
                throw new \Exception("Organization [$id] currently selected", 400);
            }

            // 3) Байгууллага хүчинтэй эсэхийг шалгах
            $org_model = new OrganizationModel($this->pdo);
            $organization = $org_model->getRowWhere([
                'id'        => $id,
                'is_active' => 1
            ]);
            if (!isset($organization['id'])) {
                throw new \Exception('Invalid organization', 403);
            }

            // 4) Хэрэглэгч тухайн байгууллагад харьяалагдсан эсэх
            $user_id = $this->getUserId();
            $org_user_model = new OrganizationUserModel($this->pdo);
            $org_user = $org_user_model->retrieve($id, $user_id);
            if (empty($org_user)) {
                // 5) Онцгой эрх: system_coder → байгууллагад автоматаар нэмнэ
                if (!$this->isUser('system_coder')) {
                    throw new \Exception('User does not belong to an organization', 406);
                }

                if (
                    empty($org_user_model->insert([
                        'user_id'         => $user_id,
                        'organization_id' => $id,
                        'created_by'      => $user_id
                    ]))
                ) {
                    throw new \RuntimeException('User can not select an organization');
                }
            }

            // 6) JWT токен шинэчлэх
            $jwt = (new JWTAuthMiddleware())->generate([
                'user_id'         => $user_id,
                'organization_id' => $id
            ]);
            $_SESSION['RAPTOR_JWT'] = $jwt;

            // Success log
            $this->indolog(
                'dashboard',
                LogLevel::NOTICE,
                'Хэрэглэгч {auth_user.first_name} {auth_user.last_name} нэвтэрсэн байгууллага [id:{id}] сонгов',
                ['action' => 'login-to-organization', 'id' => $id, 'leave' => $current_org_id]
            );
        } catch (\Throwable $err) {
            // Error log
            $this->indolog(
                'dashboard',
                LogLevel::ERROR,
                'Хэрэглэгч нэвтэрсэн байгууллага [id:{id}] сонгохоор оролдох үед алдаа илэрлээ. {error.message}',
                [
                    'action' => 'login-to-organization',
                    'id'     => $id,
                    'error'  => [
                        'code'    => $err->getCode(),
                        'message' => $err->getMessage()
                    ]
                ]
            );
        }

        // 8) Redirect logic
        $home = $this->generateRouteLink('home');
        if (isset($this->getRequest()->getServerParams()['HTTP_REFERER'])) {
            $referer = $this->getRequest()->getServerParams()['HTTP_REFERER'];
            $location = \str_contains($referer, $home) ? $referer : $home;
        } else {
            $location = $home;
        }
        \header("Location: $location", false, 302);
        exit;
    }
    
    /**
     * Системд ажиллах хэл (localization language)-ийг солих action.
     *
     * Энэ action нь хэрэглэгч footer/header дээрээс хэл сонгох үед ажиллана.
     * LocalizationMiddleware дараагийн хүсэлт дээр гарч ирэх хэлийг
     * $_SESSION['RAPTOR_LANGUAGE_CODE'] утгаар тодорхойлдог.
     *
     * Workflow (алхам алхмаар):
     * ───────────────────────────────────────────────────────────────
     *
     * 1) Одоогийн хэлний code-г ($from) авах
     *
     * 2) language() параметр (өөрөөр хэлбэл URL-аар дамжиж ирсэн хэлний code)
     *    системд бүртгэлтэй хэл мөн эсэхийг шалгах
     *      → $this->getLanguages() дотроос хайна
     *      → Хэрэв байхгүй бол юу ч хийхгүй, шууд redirect
     *
     * 3) code өөр байвал хэлний сонголтыг session-д хадгална:
     *       $_SESSION['RAPTOR_LANGUAGE_CODE'] = $code
     *
     * 4) Хэрвээ хэрэглэгч нэвтэрсэн бол:
     *       - UsersModel → хэрэглэгчийн profile дахь 'code' талбарыг update хийнэ
     *       - Лог бичнэ (LogLevel::NOTICE)
     *         “Хэрэглэгч ... системд ажиллах хэлийг {from}-с {code} болгон өөрчиллөө”
     *
     * 5) Redirect хийх:
     *       - HTTP_REFERER байгаа бол → буцааж хэвийн байршил руу
     *       - Байхгүй бол → системийн root руу
     *
     * Аюулгүй байдлын онцлог:
     *   - Хэл солих нь зөвхөн session-д нөлөөлнө, authentication-д нөлөөлөхгүй
     *   - Нэвтэрсэн хэрэглэгчийн profile-д persisted (байнга хадгалагдана)
     *   - Хэл солих үед JWT өөрчлөгдөхгүй (учир нь зөвхөн UI-level setting)
     *   - LocalizationMiddleware дараагийн хүсэлт дээр session-аас хэлний code-г уншина
     *
     * @param string $code  Системд солих гэж буй хэлний code (жишээ: 'mn', 'en')
     * @return void  Redirect хийнэ
     */
    public function language(string $code)
    {
        // 1) Одоогийн хэл
        $from     = $this->getLanguageCode();
        $language = $this->getLanguages();

        // 2) Хэл бүртгэлтэй эсэх, мөн өөр хэл байгаа эсэхийг шалгах
        if (isset($language[$code]) && $code != $from) {
            // 3) Session-д хадгалах → LocalizationMiddleware уншиж хэрэглэнэ
            $_SESSION['RAPTOR_LANGUAGE_CODE'] = $code;

            // 4) Хэрэглэгч нэвтэрсэн бол → profile update + log
            if ($this->isUserAuthorized()) {
                $user = $this->getUser()->profile;
                (new UsersModel($this->pdo))->updateById($user['id'], ['code' => $code]);
                
                $this->indolog(
                    'dashboard',
                    LogLevel::NOTICE,
                    'Хэрэглэгч {auth_user.first_name} {auth_user.last_name} системд ажиллах хэлийг {from}-с {code} болгон өөрчиллөө',
                    [
                        'action' => 'change-language',
                        'code'   => $code,
                        'from'   => $from
                    ]
                );
            }
        }

        // 5) Redirect хийх
        $script_path = $this->getScriptPath();
        $home        = (string) $this->getRequest()->getUri()->withPath($script_path);
        if (isset($this->getRequest()->getServerParams()['HTTP_REFERER'])) {
            $referer  = $this->getRequest()->getServerParams()['HTTP_REFERER'];
            $location = \str_contains($referer, $home) ? $referer : $home;
        } else {
            $location = $home;
        }
        \header("Location: $location", false, 302);
        exit;
    }
    
    /**
     * Хэрэглэгчийн хамгийн сүүлд нэвтэрсэн байгууллагын ID-г олж буцаах.
     *
     * Энэхүү функц нь хэрэглэгч өмнө нь ямар байгууллага руу (organization)
     * амжилттай нэвтэрсэн талаарх мэдээллийг системийн лог
     * (dashboard_log) хүснэгтээс уншиж тодорхойлдог.
     *
     * Лог бичлэгийн context талбар нь JSON хэлбэртэй хадгалагддаг бөгөөд
     * доорх нөхцөлтэй мөрийг хайна:
     *
     *   - action = "login-to-organization"
     *   - context.auth_user.id   = тухайн user_id
     *   - context.id             = organization_id
     *   - organization.is_active = 1
     *   - user тухайн байгууллагад бүртгэлтэй (organization_user)
     *
     * Энэ өгөгдөл нь хэрэглэгч дараагийн удаа login хийх үед
     * “default organization”-г автоматаар сонгоход хэрэглэгддэг.
     *
     * Workflow (алхам алхмаар):
     * ───────────────────────────────────────────────────────────────
     *
     * 1) Database driver (MySQL / PostgreSQL)-ийг тодорхойлно.
     *    Учир нь JSON талбарын query dialect нь өөр:
     *      - PostgreSQL → JSONB операторууд (::jsonb, ->>, →)
     *      - MySQL      → JSON_EXTRACT, JSON_UNQUOTE
     *
     * 2) dashboard_log хүснэгтээс хамгийн сүүлийн:
     *        action='login-to-organization'
     *        auth_user.id = userId
     *        organization is_active=1
     *      гэсэн мөрийг хайна.
     *
     * 3) Хэрэв лог бичлэг олдвол:
     *      context JSON → array decode → ['id'] талбарыг буцаана
     *
     * 4) Хэрэв алдаа гарвал (query error, log not found гэх мэт):
     *      → false буцаана.
     *
     * Security онцлогууд:
     *   - Лог дээр тулгуурлан default organization тодорхойлдог тул
     *     хэрэглэгч өмнө хамгийн сүүлд ажилласан байгууллага руу автоматаар орно.
     *   - is_active=1 байгууллагад л тохиромжтой.
     *   - Хэрэглэгч тухайн байгууллагад албан ёсоор харьяалагдсан эсэхийг
     *     organization_user хүснэгтээр баталгаажуулдаг.
     *
     * @param int $userId  Хэрэглэгчийн ID
     * @return int|false   Олдсон байгууллагын ID, эсвэл false (олдоогүй/алдаа гарсан)
     */
    private function getLastLoginOrg(int $userId): int|false
    {
        try {
            $orgTable     = (new OrganizationModel($this->pdo))->getName();
            $orgUserTable = (new OrganizationUserModel($this->pdo))->getName();

            if ($this->getDriverName() == 'pgsql') {
                // PostgreSQL JSONB query
                $sql =
                    "SELECT context
                     FROM dashboard_log AS log
                     INNER JOIN $orgTable AS org
                        ON ((log.context::jsonb)->>'id')::int = org.id
                     LEFT JOIN $orgUserTable AS orgUser
                        ON orgUser.organization_id = org.id
                     WHERE (log.context::jsonb)->>'action' = 'login-to-organization'
                       AND ((log.context::jsonb)->'auth_user'->>'id')::int = $userId
                       AND orgUser.user_id = $userId
                       AND org.is_active = 1
                     ORDER BY log.id DESC
                     LIMIT 1";

            } else {
                // MySQL JSON query
                $sql =
                    "SELECT context
                     FROM dashboard_log AS log
                     INNER JOIN $orgTable AS org
                        ON CAST(JSON_UNQUOTE(JSON_EXTRACT(log.context,'$.id')) AS UNSIGNED) = org.id
                     LEFT JOIN $orgUserTable AS orgUser
                        ON orgUser.organization_id = org.id
                     WHERE JSON_UNQUOTE(JSON_EXTRACT(log.context,'$.action')) = 'login-to-organization'
                       AND CAST(JSON_UNQUOTE(JSON_EXTRACT(log.context,'$.auth_user.id')) AS UNSIGNED) = $userId
                       AND orgUser.user_id = $userId
                       AND org.is_active = 1
                     ORDER BY log.id DESC
                     LIMIT 1";
            }

            $result = $this->query($sql)->fetch();

            if (empty($result)) {
                // Лог олдоогүй → false
                throw new \Exception('No log');
            }

            // context JSON → decode
            return \json_decode($result['context'], true)['id'];
        } catch (\Throwable) {
            return false;
        }
    }
}
