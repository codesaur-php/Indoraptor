<?php

namespace Raptor\User;

use Twig\TwigFunction;

use Psr\Log\LogLevel;

use codesaur\Template\MemoryTemplate;

use Raptor\Authentication\ForgotModel;
use Raptor\Authentication\SignupModel;
use Raptor\Content\FileController;
use Raptor\Organization\OrganizationModel;
use Raptor\Organization\OrganizationUserModel;
use Raptor\RBAC\UserRole;
use Raptor\RBAC\Roles;
use Raptor\Content\ReferenceModel;
use Raptor\Mail\Mailer;
use Raptor\Log\Logger;

/**
 * Class UsersController
 *
 * Хэрэглэгчийн бүртгэл, мэдээлэл засварлалт, RBAC дүрийн удирдлага,
 * байгууллагын хамаарал тохируулах, нууц үг солих зэрэг
 * хэрэглэгчтэй холбоотой бүх server-side логикийг агуулсан
 * Indoraptor Dashboard-ийн үндсэн Controller юм.
 *
 * --------------------------------------------------------------
 * 🧩 Архитектур - PDO автоматаар хэрхэн ирдэг вэ?
 * --------------------------------------------------------------
 *  Raptor\Controller нь:
 *
 *      use \codesaur\DataObject\PDOTrait;
 *
 *  гэдэг trait-ийг ашигладаг. PDOTrait нь `$pdo` шинж чанарыг
 *  controller-ийн объект дээр үүсгэж өгдөг.
 *
 *  Framework-ийн түвшинд DatabaseConnectMiddleware нь:
 *
 *      $request = $request->withAttribute('pdo', $pdo);
 *
 *  гэж PSR-7 ServerRequest дотор `pdo` attribute-ийг суулгадаг.
 *
 *  Controller нь BaseController::__construct() дотор:
 *
 *      $this->pdo = $request->getAttribute('pdo');
 *
 *  хэлбэрээр автоматаар авч `$this->pdo` болгон тохируулдаг.
 *
 * ✔ Энэ механизмаар бүх Model-классуудыг:
 *      new UsersModel($this->pdo)
 *      new Roles($this->pdo)
 *      new OrganizationModel($this->pdo)
 *  гэх мэтээр шууд хэрэглэнэ.
 *
 * --------------------------------------------------------------
 * 🧩 Хамааралтай модулиуд
 * --------------------------------------------------------------
 *  • UsersModel - хэрэглэгчийн үндсэн өгөгдлийн хүснэгт
 *  • RBAC => Roles / UserRole - RBAC дүрийн систем
 *  • OrganizationModel / OrganizationUserModel - байгууллагын хамаарал
 *  • SignupModel / ForgotModel - бүртгүүлэх болон нууц үг сэргээх хүсэлт
 *  • FileController - зураг upload удирдлага (profile photo)
 *  • Logger - үйлдлийн протокол
 *  • DashboardTrait - Twig Dashboard integration
 *
 * --------------------------------------------------------------
 * 🔐 Аюулгүй байдал ба Permission
 * --------------------------------------------------------------
 *  • `isUserCan()` функцээр бүх үйлдэл зөвшөөрөл шалгадаг
 *  • Root хэрэглэгчийг хамгаална (id = 1)
 *  • Хэрэглэгч өөрийгөө устгах боломжгүй
 *  • RBAC aliases (common, org1, org2 ...) дагуу role binding
 *
 * --------------------------------------------------------------
 * 📡 Response төрөл
 * --------------------------------------------------------------
 *  • Dashboard UI (twig template)
 *  • JSON (AJAX хүсэлтүүдэд)
 *  • Modal templates
 *
 * --------------------------------------------------------------
 * 📝 Logging
 * --------------------------------------------------------------
 *  Бүх томоохон үйлдэл indolog() руу дараах бүтэцтэйгээр бичигдэнэ:
 *
 *      $this->indolog(
 *          'users',
 *          LogLevel::NOTICE | ERROR | ALERT,
 *          'Мессеж',
 *          ['action' => '...', 'id' => ..., 'record' => ...]
 *      );
 *
 * --------------------------------------------------------------
 * 📦 File upload
 * --------------------------------------------------------------
 *  FileController-с удамшдаг тул дараах боломжтой:
 *      $this->setFolder("/users/{$id}");
 *      $this->allowImageOnly();
 *      $photo = $this->moveUploaded('photo');
 *
 * --------------------------------------------------------------
 * ✔ Энэ класст багтах үндсэн үйлдлүүд:
 * --------------------------------------------------------------
 *  • index()               - хэрэглэгчийн Dashboard view
 *  • list()                - хэрэглэгчдийн JSON жагсаалт
 *  • insert()              - шинэ хэрэглэгч үүсгэх
 *  • update($id)           - хэрэглэгчийн мэдээлэл засварлах
 *  • view($id)             - хэрэглэгчийн дэлгэрэнгүй харах
 *  • deactivate()          - хэрэглэгчийг идэвхгүй болгох
 *  • requestsModal()       - signup / forgot хүсэлтүүдийг харах
 *  • signupApprove()       - шинээр бүртгүүлэх хүсэлтийг зөвшөөрөх
 *  • signupDeactivate()    - signup хүсэлтийг устгах
 *  • setPassword($id)      - хэрэглэгчийн нууц үг тохируулах
 *  • setOrganization($id)  - хэрэглэгчийн байгууллага тохируулах
 *  • setRole($id)          - хэрэглэгчийн RBAC дүр тохируулах
 *
 * @package Raptor\User
 */
class UsersController extends FileController
{
    use \Raptor\Template\DashboardTrait;
    
    /**
     * Хэрэглэгчдийн жагсаалтын Dashboard хуудсыг нээх
     *
     * --------------------------------------------------------------
     * 📌 Үндсэн үүрэг
     * --------------------------------------------------------------
     *  - system_user_index эрхтэй эсэхийг шалгана
     *  - Twig dashboard layout ашиглан user-index.html темплейтийг
     *    render хийнэ
     *  - Хэрэв алдаа гарвал dashboardProhibited() ашиглан
     *    хэрэглэгчдэд ойлгомжтой error UI үзүүлнэ
     *
     * --------------------------------------------------------------
     * 🔐 Permission logic
     * --------------------------------------------------------------
     *  Энэ хуудсыг зөвхөн `system_user_index` эрхтэй хэрэглэгч 
     *  нээх боломжтой. Хэрэв эрхгүй бол:
     *
     *      throw new \Exception($this->text('system-no-permission'), 401);
     *
     * --------------------------------------------------------------
     * ⚙ Алдаа барих ба лог бичилт
     * --------------------------------------------------------------
     *  try/catch/finally блок:
     *
     *  ✔ try — UI-г хэвийн нээнэ  
     *  ✔ catch — алдаа гарвал Dashboard UI дээр error box харуулна  
     *  ✔ finally — indolog() руу протокол тэмдэглэнэ:
     *      - Амжилттай нээсэн → LogLevel::NOTICE  
     *      - Алдаатай → LogLevel::ERROR  
     *
     *  Логт дараах context орно:
     *      ['action' => 'index', ...]  
     *
     * --------------------------------------------------------------
     * 📡 Response
     * --------------------------------------------------------------
     *  - UI response (Twig Dashboard)
     *  - JSON response буцаахгүй
     *
     * --------------------------------------------------------------
     * 🧩 Ашиглагдах template:
     * --------------------------------------------------------------
     *  /application/raptor/user/user-index.html
     *
     * @return void
     */
    public function index()
    {
        try {
            // RBAC эрх шалгана – хэрэглэгчид хэрэглэгчийн жагсаалт үзэх эрх байх ёстой
            if (!$this->isUserCan('system_user_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            // Dashboard зориулалтын Twig wrapper дотор template-ээ ачаална
            $dashboard = $this->twigDashboard(
                __DIR__ . '/user-index.html'
            );
             // Гарчгийг локальчилж, template-руу дамжуулна
            $dashboard->set('title', $this->text('users'));
            $dashboard->render();
        } catch (\Throwable $err) {
            // Ямар нэгэн алдаа гарвал алдааны dashboard-г үзүүлнэ
            $this->dashboardProhibited(
                "Хэрэглэгчдийн жагсаалтыг нээх үед алдаа гарлаа.<br/><br/>{$err->getMessage()}",
                $err->getCode()
            )->render();
        } finally {
            // Энэ action-ийн лог протокол – амжилттай эсэхээс үл хамааран бичнэ
            $context = ['action' => 'index'];
            if (isset($err) && $err instanceof \Throwable) {
                // Алдаатай төгссөн тохиолдолд ERROR level
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгчдийн жагсаалтыг нээх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                // Амжилттай үзсэн бол NOTICE level
                $level = LogLevel::NOTICE;
                $message = 'Хэрэглэгчдийн жагсаалтыг үзэж байна';
            }
            // users logger-д бичих
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    /**
     * Хэрэглэгчдийн жагсаалтыг JSON хэлбэрээр буцаах API.
     *
     * Гол үүрэг:
     *  - RBAC эрх (`system_user_index`) шалгана
     *  - users хүснэгтээс үндсэн мэдээлэл татна
     *  - UserRole / Roles хүснэгтээр дамжуулж хэрэглэгч бүрийн ролуудыг нэгтгэнэ
     *  - OrganizationUser / Organization хүснэгтээр дамжуулж байгууллагын мэдээлэл нэмж нэгтгэнэ
     *  - Эцэст нь нэг массив болгон нэгтгээд JSON-р буцаана:
     *      [
     *          {
     *              id, username, email, is_active, roles[], organizations[]
     *          },
     *          ...
     *      ]
     *
     * Ашиглагдах үндсэн газар:
     *  - Admin Dashboard-ийн Users list UI (AJAX-аар хүсэлт явуулж table-г populate хийхэд)
     *
     * @return void
     */
    public function list()
    {
        try {
            // RBAC эрх шалгах – хэрэглэгчийн жагсаалт авах эрхтэй эсэх
            if (!$this->isUserCan('system_user_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $table = (new UsersModel($this->pdo))->getName();
            $users_infos = $this->query(
                "SELECT id,photo,photo_size,last_name,first_name,username,phone,email,is_active FROM $table ORDER BY id"
            )->fetchAll();
            
            $users = [];
            foreach ($users_infos as $user) {
                $users[$user['id']] = $user;
            }
            
            // Хэрэглэгч бүрийн role-уудыг (alias_name) хэлбэрээр нэгтгэх
            $user_role_table = (new UserRole($this->pdo))->getName();
            $roles_table = (new Roles($this->pdo))->getName();
            $select_user_role =
                'SELECT t1.role_id, t1.user_id, t2.name, t2.alias ' . 
                "FROM $user_role_table as t1 INNER JOIN $roles_table as t2 ON t1.role_id=t2.id";
            $user_role = $this->query($select_user_role)->fetchAll();
            // user_id-гаар нь users[$id]['roles'][] массив руу цуглуулна
            \array_walk($user_role, function($value) use (&$users) {
                if (isset($users[$value['user_id']])) {
                    if (!isset($users[$value['user_id']]['roles'])) {
                        $users[$value['user_id']]['roles'] = [];
                    }
                    $users[$value['user_id']]['roles'][] = "{$value['alias']}_{$value['name']}";
                }
            });
            
            // Байгууллагын мэдээллийг хэрэглэгч бүр дээр нэгтгэх
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $org_user_table = (new OrganizationUserModel($this->pdo))->getName();
            $select_orgs_users =
                'SELECT t1.user_id, t1.organization_id as id, t2.name, t2.alias ' .
                "FROM $org_user_table as t1 INNER JOIN $org_table as t2 ON t1.organization_id=t2.id " .
                'WHERE t2.is_active=1';
            $org_users = $this->query($select_orgs_users)->fetchAll();
            // user_id-гаар нь users[$id]['organizations'][] массив руу байгууллагын мэдээллийг нэмнэ
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
            
            // Амжилттай status=success, list = хэрэглэгчдийн массив (0-based index-ээр)
            $this->respondJSON([
                'status' => 'success',
                'list' => \array_values($users)
            ]);
        } catch (\Throwable $e) {
            // Алдаа гарвал зөвхөн мессеж, HTTP кодыг JSON-оор буцаана
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }
    
    /**
     * Шинэ хэрэглэгч үүсгэх action.
     *
     * Хоёр янзаар ажиллана:
     *
     *  1) GET /users/insert
     *     - Хэрэглэгч үүсгэх form-тай Dashboard хуудсыг рендерлэнэ
     *     - `user-insert.html` template-д идэвхтэй байгууллагуудыг (organizations.is_active=1) дамжуулна
     *
     *  2) POST /users/insert
     *     - Request body-оос хэрэглэгчийн мэдээлэл уншина
     *     - username, email, password-г шалгана
     *       * password хоосон байвал санамсаргүй 10-byte (20 hex тэмдэгт) нууц үг үүсгэнэ
     *     - UsersModel ашиглан users хүснэгтэд insert хийнэ
     *     - Хэрэв organization_id ирсэн бол OrganizationUserModel-д харьяалал үүсгэнэ
     *     - FileController::moveUploaded() ашиглан photo upload хийж, users.photo_* талбаруудыг шинэчилнэ
     *     - Амжилттай бол JSON {status: success, message: ...} буцаана
     *
     * Лог:
     *  - finally хэсэгт `indolog('users', ...)` ашиглан create үйлдлийн протокол үлдээнэ
     *
     * @return void
     */
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_user_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            // Ажиллах Model-ууд
            $model = new UsersModel($this->pdo);
            $orgModel = new OrganizationModel($this->pdo);
            // HTTP method шалгаад POST үед л DB insert хийнэ, бусад үед form харуулна
            if ($this->getRequest()->getMethod() == 'POST') {
                // -----------------------------
                // POST – хэрэглэгч үүсгэх
                // -----------------------------
                $payload = $this->getParsedBody();
                
                // Заавал байх ёстой талбаруудыг шалгах (username / email)
                if (empty($payload['username']) || empty($payload['email'])
                    || \filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                // Нууц үг хоосон байвал санамсаргүй үүсгэнэ, байвал шууд ашиглана
                if (empty($payload['password'])) {
                    $bytes = \random_bytes(10);
                    $password = \bin2hex($bytes);
                } else {
                    $password = $payload['password'];
                }
                // Нууц үгийг bcrypt-аар hash хийж DB-д хадгалах бэлэн болно
                $payload['password'] = \password_hash($password, \PASSWORD_BCRYPT);
                
                // POST дээр ирсэн organization (optional) – дараа нь OrganizationUserModel-д ашиглана
                $post_organization = $payload['organization'] ?? null;
                unset($payload['organization']);
                
                // created_by-г одоогийн хэрэглэгчийн ID-аар тавьж insert хийнэ
                $record = $model->insert($payload + ['created_by' => $this->getUserId()]);
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                // Client-д зориулсан JSON хариу – амжилттай үүссэн тухай
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                // Хэрэв organization сонгосон бол хэрэглэгчийг тухайн байгууллагад холбох
                if (!empty($post_organization)) {
                    $organization = \filter_var($post_organization, \FILTER_VALIDATE_INT);
                    if ($organization !== false
                        && !empty($orgModel->getRowWhere(['id' => $organization, 'is_active' => 1]))
                    ) {
                        (new OrganizationUserModel($this->pdo))->insert(
                            ['user_id' => $record['id'], 'organization_id' => $organization, 'created_by' => $this->getUserId()]
                        );
                    }
                }
                
                // Хэрэглэгчийн зураг upload хийх боломжийг нээх
                // /users/{id} гэсэн хавтас руу байрлуулна -> {id} insert хийсэн шинэ бичлэгийн дугаар
                $this->setFolder("/{$model->getName()}/{$record['id']}");
                $this->allowImageOnly(); // зөвхөн зурган файл зөвшөөрнө
                $photo = $this->moveUploaded('photo');
                if ($photo) {
                    // Хэрэв зураг амжилттай upload болсон бол тухайн хэрэглэгчийн photo_* талбаруудыг шинэчилнэ
                    $record = $model->updateById(
                        $record['id'],
                        [
                            'photo' => $photo['path'],
                            'photo_file' => $photo['file'],
                            'photo_size' => $photo['size']
                        ]
                    );
                }
            } else {
                // -----------------------------
                // GET – хэрэглэгч үүсгэх form-тай Dashboard хуудсыг харуулах
                // -----------------------------
                $dashboard = $this->twigDashboard(
                    __DIR__ . '/user-insert.html',
                    [
                        // Зөвхөн идэвхтэй байгууллагуудыг сонгож form-д өгнө
                        'organizations' => $orgModel->getRows(['WHERE' => 'is_active=1'])
                    ]
                );
                $dashboard->set('title', $this->text('create-new-user'));
                $dashboard->render();
            }
        } catch (\Throwable $err) {
            // Алдаа гарсан үед:
            if ($this->getRequest()->getMethod() == 'POST') {
                // Хэрэв POST хүсэлт байсан бол JSON алдаа буцаах хэрэгтэй
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                // Харин form нээх явцад алдаа гарвал dashboard алдааны дэлгэц харуулна
                $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // Энэ action-ийн лог протокол
            $context = ['action' => 'create'];
            if (isset($err) && $err instanceof \Throwable) {
                // Алдаатай дууссан тохиолдолд ERROR level
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгч үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                // Амжилттай шинэ хэрэглэгч үүсгэсэн үед INFO level
                $level = LogLevel::INFO;
                $message = 'Хэрэглэгч [{record.username}] {record.id} дугаартай амжилттай үүслээ';
                // POST амжилттай тул $record–г лог дээр нь хадгална
                $context += ['id' => $record['id'], 'record' => $record];
            } else {
                // Зөвхөн create form-ийг нээсэн үед NOTICE level
                $level = LogLevel::NOTICE;
                $message = 'Хэрэглэгч үүсгэх үйлдлийг эхлүүллээ';
            }
            // users logger-д бичих
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
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
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            if ($this->getRequest()->getMethod() == 'PUT') {
                $payload = $this->getParsedBody();
                if (empty($payload['username']) || empty($payload['email'])
                    || \filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload['email'] = \filter_var($payload['email'], \FILTER_VALIDATE_EMAIL);
                if (!empty($payload['password'])) {
                    $payload['password'] = \password_hash($payload['password'], \PASSWORD_BCRYPT);
                }
                $post_organizations = \filter_var($payload['organizations'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY) ?: [];
                unset($payload['organizations']);
                $post_roles = \filter_var($payload['roles'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY) ?: [];
                unset($payload['roles']);

                $existing_username = $model->getRowWhere(['username' => $payload['username']]);
                $existing_email = $model->getRowWhere(['email' => $payload['email']]);
                if (!empty($existing_username) && $existing_username['id'] != $id) {
                    throw new \Exception($this->text('user-exists') . " username => [{$payload['username']}], id => {$existing_username['id']}", 403);
                } elseif (!empty($existing_email) && $existing_email['id'] != $id) {
                    throw new \Exception($this->text('user-exists') . " email => [{$payload['email']}], id => {$existing_email['id']}", 403);
                }
                
                if ($payload['photo_removed'] == 1) {
                    if (\file_exists($record['photo_file'])) {
                        \unlink($record['photo_file']);
                        $record['photo_file'] = '';
                    }
                    $payload['photo'] = '';
                    $payload['photo_file'] = '';
                    $payload['photo_size'] = 0;
                }
                unset($payload['photo_removed']);
                
                $this->setFolder("/{$model->getName()}/$id");
                $this->allowImageOnly();
                $photo = $this->moveUploaded('photo');
                if ($photo) {
                    if (!empty($record['photo_file'])
                        && \file_exists($record['photo_file'])
                    ) {
                        \unlink($record['photo_file']);
                    }
                    $payload['photo'] = $photo['path'];
                    $payload['photo_file'] = $photo['file'];
                    $payload['photo_size'] = $photo['size'];
                }
                
                $updates = [];
                foreach ($payload as $field => $value) {
                    if ($record[$field] != $value) {
                        $updates[] = $field;
                    }
                }
                
                if ($this->configureOrgs($id, $post_organizations)) {
                    $updates[] = 'organizations-configure';
                }
                if ($this->configureRoles($id, $post_roles)) {
                    $updates[] = 'roles-configure';
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
                    'type' => 'primary',
                    'status' => 'success',
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                $orgModel = new OrganizationModel($this->pdo);
                $orgUserModel = new OrganizationUserModel($this->pdo);
                $organizations = $orgModel->getRows(['WHERE' => 'is_active=1']);
                $vars = ['record' => $record, 'organizations' => $organizations];
                $select_org_ids =
                    'SELECT ou.organization_id as id ' .
                    "FROM {$orgUserModel->getName()} as ou INNER JOIN {$orgModel->getName()} as o ON ou.organization_id=o.id " .
                    "WHERE ou.user_id=$id AND o.is_active=1";
                $org_ids = $this->query($select_org_ids)->fetchAll();
                $current_organizations = [];
                foreach ($org_ids as $org) {
                    $current_organizations[] = $org['id'];
                }
                $vars['current_organizations'] = $current_organizations;
                
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

                $rolesModel = new Roles($this->pdo);
                $roles = \array_map(function() { return []; }, \array_flip(\array_keys($rbacs)));
                $rbac_roles = $this->query(
                    "SELECT id,alias,name,description FROM {$rolesModel->getName()}"
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

                $userRoleModel = new UserRole($this->pdo);
                $select_user_roles =
                    "SELECT rur.role_id FROM {$userRoleModel->getName()} as rur INNER JOIN {$rolesModel->getName()} as rr ON rur.role_id=rr.id " .
                    "WHERE rur.user_id=$id";
                $current_roles_rows = $this->query($select_user_roles)->fetchAll();
                $current_role = [];
                foreach ($current_roles_rows as $row) {
                    $current_role[] = $row['role_id'];
                }
                $vars['current_roles'] = $current_role;                
                $dashboard = $this->twigDashboard(__DIR__ . '/user-update.html', $vars);
                $dashboard->set('title', $this->text('edit-user'));
                $dashboard->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'update', 'id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '[{record.username}] {record.id} дугаартай хэрэглэгчийн мэдээллийг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '[{record.username}] {record.id} дугаартай хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг эхлүүллээ';
                $context += ['record' => $record, 'current_roles' => $current_role, 'current_organizations' => $current_organizations];
            }
            $this->indolog('users', $level, $message, $context);
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
            $record = $model->getRowWhere([
                'id' => $id
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $record['rbac_users'] = $this->retrieveUsersDetail($record['created_by'], $record['updated_by']);
            
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $org_user_table = (new OrganizationUserModel($this->pdo))->getName();
            $select_user_orgs =
                'SELECT t2.name, t2.alias, t2.id ' .
                "FROM $org_user_table as t1 INNER JOIN $org_table as t2 ON t1.organization_id=t2.id " .
                "WHERE t2.is_active=1 AND t1.user_id=$id";
            $organizations = $this->query($select_user_orgs)->fetchAll();

            $roles_table = (new Roles($this->pdo))->getName();
            $user_role_table = (new UserRole($this->pdo))->getName();
            $select_user_roles =
                'SELECT ' . ($this->getDriverName() == 'pgsql' ? "t2.alias || '_' || t2.name" : 'CONCAT(t2.alias, "_", t2.name)') . ' as name ' . 
                "FROM $user_role_table as t1 INNER JOIN $roles_table as t2 ON t1.role_id=t2.id " .
                "WHERE t1.user_id=$id";
            $roles = $this->query($select_user_roles)->fetchAll();
            
            $dashboard = $this->twigDashboard(
                __DIR__ . '/user-view.html',
                ['record' => $record, 'roles' => $roles, 'organizations' => $organizations]
            );
            $dashboard->set('title', $this->text('user'));
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'view', 'id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай хэрэглэгчийн мэдээллийг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.username} хэрэглэгчийн мэдээллийг үзэж байна';
                $context += ['record' => $record, 'roles' => $roles, 'organizations' => $organizations];
            }
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    public function deactivate()
    {
        try {
            if (!$this->isUserCan('system_user_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
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
            
            $model = new UsersModel($this->pdo);
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
                $message = 'Хэрэглэгчийг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{server_request.body.id} дугаартай [{server_request.body.name}] хэрэглэгчийг [{server_request.body.reason}] шалтгаанаар идэвхгүй болголоо';
            }
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    public function requestsModal(string $table)
    {
        try {
            if (!$this->isUserCan('system_user_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            if (!\in_array($table, ['forgot', 'signup'])) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            
            $model = $table == 'forgot' ? new ForgotModel($this->pdo) : new SignupModel($this->pdo);
            $rows = $model->getRows(['WHERE' => 'is_active!=999', 'ORDER BY' => 'created_at Desc']);
            $template = $this->twigTemplate(__DIR__ . "/$table-index-modal.html", ['rows' => $rows]);
            $template->addFunction(new TwigFunction('isExpired', function (string $date, int $minutes = 5): bool
            {
                $now_date = new \DateTime();
                $then = new \DateTime($date);
                $diff = $then->diff($now_date);
                return $diff->y > 0 || $diff->m > 0 || $diff->d > 0 || $diff->h > 0 || $diff->i > $minutes;
            }));
            $template->render();
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'requests-modal', 'table' => $table];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгчдийн мэдээллийн [{table}] хүснэгтийг нээж үзэх хүсэлт алдаатай байна';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '[{table}] хүсэлтүүдийн жагсаалтыг үзэж байна';
                $context += ['count-rows' => \count($rows)];
            }
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    public function signupApprove()
    {
        try {
            if (!$this->isUserCan('system_user_insert')) {
                throw new \Exception('No permission for an action [approval]!', 401);
            }
            
            $parsedBody = $this->getParsedBody();
            if (empty($parsedBody['id'])
                || !\filter_var($parsedBody['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($parsedBody['id'], \FILTER_VALIDATE_INT);
            
            $signupModel = new SignupModel($this->pdo);
            $signup = $signupModel->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($signup)) {
                throw new \Exception($this->text('no-record-selected'));
            }            
            $payload = [
                'username' => $signup['username'],
                'password' => $signup['password'],
                'email' => $signup['email'],
                'code' => $signup['code']
            ];
            
            $model = new UsersModel($this->pdo);
            $existing = $this->prepare("SELECT id FROM {$model->getName()} WHERE username=:username OR email=:email");            
            $existing->bindParam(':email', $signup['email'], \PDO::PARAM_STR, $model->getColumn('email')->getLength());
            $existing->bindParam(':username', $signup['username'], \PDO::PARAM_STR, $model->getColumn('username')->getLength());
            if ($existing->execute() && !empty($existing->fetch())) {
                throw new \Exception($this->text('user-exists') . ": username/email => {$signup['username']}/{$signup['email']}", 403);
            }
            
            $record = $model->insert($payload);
            if (empty($record)) {
                throw new \Exception('Failed to create user');
            }
            $signupModel->updateById(
                $id,
                [
                    'user_id' => $record['id'],
                    'is_active' => 2,
                    'updated_by' => $this->getUserId()
                ]
            );
            $organization_id = \filter_var($parsedBody['organization_id'] ?? 0, \FILTER_VALIDATE_INT);
            if (empty($organization_id)) {
                $organization_id = 1;
            }
            $orgModel = new OrganizationModel($this->pdo);
            $organization = $orgModel->getRowWhere([
                'id' => $organization_id,
                'is_active' => 1
            ]);
            if (!empty($organization)) {
                $user_org = (new OrganizationUserModel($this->pdo))->insert([
                    'user_id' => $record['id'],
                    'organization_id' => $organization_id,
                    'created_by' => $this->getUserId()
                ]);
                if (!empty($user_org)) {
                    $record['organizations'] = [$organization];
                }
            }
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-insert-success'),
                'record' => $record
            ]);
            
            $code = $this->getLanguageCode();
            $referenceModel = new ReferenceModel($this->pdo);
            $referenceModel->setTable('templates');
            $reference = $referenceModel->getRowWhere(
                [
                    'c.code' => $code,
                    'p.keyword' => 'approve-new-user',
                    'p.is_active' => 1
                ]
            );
            if (!empty($reference['localized']['content'][$code])) {
                $localized = $reference['localized'];
                $template = new MemoryTemplate();
                $template->source($localized['content'][$code]);
                $template->set('email', $signup['email']);
                $template->set('login', $this->generateRouteLink('login', [], true));
                $template->set('username', $signup['username']);
                (new Mailer($this->pdo))
                    ->mail($signup['email'], null, $localized['title'][$code], $template->output())
                    ->send();
            }
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'signup-approve'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг зөвшөөрч системд нэмэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = 'Шинэ бүртгүүлсэн {signup.username} нэртэй {signup.email} хаягтай хүсэлтийг зөвшөөрч системд хэрэглэгчээр нэмлээ';
                $context += ['signup' => $signup, 'record' => $record];
            }
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    public function signupDeactivate()
    {
        try {
            if (!$this->isUserCan('system_user_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }            
            $deactivated = (new SignupModel($this->pdo))->deactivateById(
                (int)$payload['id'],
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
            $context = ['action' => 'signup-deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг идэвхгүй болгох явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '[{server_request.body.name}] хэрэглэгчээр бүртгүүлэх хүсэлтийг идэвхгүй болгов';
            }
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    public function setPassword(int $id)
    {
        try {
            if (!$this->isUser('system_coder')
                && $this->getUser()->profile['id'] != $id
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new UsersModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            if ($this->getRequest()->getMethod() == 'POST') {
                $parsedBody = $this->getParsedBody();
                $password = $parsedBody['password'] ?? null;            
                $password_retype = $parsedBody['password_retype'] ?? null;
                if (empty($password) || $password != $password_retype) {
                    throw new \Exception($this->text('password-must-match'), 400);
                }                
                $updated = $model->updateById(
                    $id,
                    [
                        'updated_by' => $this->getUserId(),
                        'updated_at' => \date('Y-m-d H:i:s'),
                        'password' => \password_hash($password, \PASSWORD_BCRYPT)
                    ]
                );
                if (empty($updated)) {
                    throw new \Exception("Can't reset user [{$record['username']}] password", 500);
                }
                return $this->respondJSON([
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('set-new-password-success')
                ]);
            } else {
                $this->twigTemplate(
                    __DIR__ . '/user-set-password-modal.html',
                    ['profile' => $record]
                )->render();
            }
        } catch (\Throwable $err) {            
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $err->getMessage()
                ], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {            
            $context = ['action' => 'set-password', 'id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай хэрэглэгчийн нууц үг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $context += ['record' => $record];
                $message = '{id} дугаартай [{record.username}] хэрэглэгчийн нууц ';
                if ($this->getRequest()->getMethod() == 'POST') {
                    $level = LogLevel::INFO;
                    $message .= 'үгийг амжилттай шинэчлэв';
                } else {
                    $level = LogLevel::NOTICE;
                    $message .= 'үг өөрчлөх үйлдлийг эхлүүллээ';
                }
            }
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    public function setOrganization(int $id)
    {
        try {            
            if (!$this->isUserCan('system_user_organization_set')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
                   
            $model = new UsersModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            if ($this->getRequest()->getMethod() == 'POST') {
                $post_organizations = \filter_var($this->getParsedBody()['organizations'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY) ?: [];
                if ($id == 1
                    && (empty($post_organizations) || !\in_array(1, $post_organizations))
                ) {
                    throw new \Exception('Root user must belong to a system organization', 503);
                }
                if (!$this->configureOrgs($id, $post_organizations)) {
                    throw new \Exception('No updates');
                }
                return $this->respondJSON([
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                $orgModel = new OrganizationModel($this->pdo);
                $orgUserModel = new OrganizationUserModel($this->pdo);
                $response = $this->query(
                    'SELECT ou.organization_id as id ' .
                    "FROM {$orgUserModel->getName()} as ou INNER JOIN {$orgModel->getName()} as o ON ou.organization_id=o.id " .
                    "WHERE ou.user_id=$id AND o.is_active=1"
                );
                $current_organizations = [];
                foreach ($response as $org) {
                    $current_organizations[] = $org['id'];
                }
                $vars = [
                    'profile' => $record,
                    'current_organizations' => $current_organizations,
                    'organizations' => $orgModel->getRows(['WHERE' => 'is_active=1'])
                ];
                $this->twigTemplate(__DIR__ . '/user-set-organization-modal.html', $vars)->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $err->getMessage()
                ], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'set-organization', 'id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай хэрэглэгчийн байгууллага тохируулах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $context += ['record' => $record];
                $message = '{id} дугаартай [{record.username}] хэрэглэгчийн байгууллага ';
                if ($this->getRequest()->getMethod() == 'POST') {
                    $level = LogLevel::INFO;
                    $message .= 'амжилттай тохируулав';
                } else {
                    $level = LogLevel::NOTICE;
                    $message .= 'тохируулах үйлдлийг эхлүүллээ';
                    $context += ['current_organizations' => $current_organizations];
                }
            }
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    private function configureOrgs(int $id, array $orgSets): bool
    {
        $configured = false;
        try {            
            if (!$this->isUserCan('system_user_organization_set')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $logger = new Logger($this->pdo);
            $logger->setTable('users');
            $auth_user = [
                'id' => $this->getUser()->profile['id'],
                'username' => $this->getUser()->profile['username'],
                'first_name' => $this->getUser()->profile['first_name'],
                'last_name' => $this->getUser()->profile['last_name'],
                'phone' => $this->getUser()->profile['phone'],
                'email' => $this->getUser()->profile['email']
            ];
            
            $model = new UsersModel($this->pdo);
            $orgModel = new OrganizationModel($this->pdo);
            $orgUserModel = new OrganizationUserModel($this->pdo);
            $sql =
                'SELECT t1.id, t1.user_id, t1.organization_id, t2.name as organization_name, t3.username ' .
                "FROM {$orgUserModel->getName()} t1 INNER JOIN {$orgModel->getName()} t2 ON t1.organization_id=t2.id LEFT JOIN {$model->getName()} t3 ON t1.user_id=t3.id " .
                "WHERE t1.user_id=$id AND t2.is_active=1 AND t3.is_active=1";
            $userOrgs = $this->query($sql)->fetchAll();
            $organizationIds = \array_flip($orgSets);
            foreach ($userOrgs as $row) {
                if (isset($organizationIds[$row['organization_id']])) {
                    unset($organizationIds[$row['organization_id']]);
                } elseif ($row['organization_id'] == 1 && $id == 1) {
                    // can't strip root user from system organization!
                } elseif ($orgUserModel->deleteById($row['id'])) {
                    $configured = true;
                    $logger->log(
                        LogLevel::ALERT,
                        '[{organization_name}:{organization_id}] байгууллагаас [{username}:{user_id}] хэрэглэгчийг хаслаа',
                        ['action' => 'strip-organization'] + $row + ['auth_user' => $auth_user]
                    );
                }
            }
            foreach (\array_keys($organizationIds) as $org_id) {
                if (!empty($orgUserModel->insert(
                    ['user_id' => $id, 'organization_id' => $org_id, 'created_by' => $this->getUserId()]))
                ) {
                    $configured = true;
                    $logger->log(
                        LogLevel::ALERT,
                        '{organization_id}-р байгууллагад {user_id}-р хэрэглэгчийг нэмлээ',
                        ['action' => 'set-organization', 'user_id' => $id, 'organization_id' => $org_id, 'auth_user' => $auth_user]
                    );
                }
            }
        } catch (\Throwable) {}
        return $configured;
    }
    
    public function setRole(int $id)
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new UsersModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            if ($this->getRequest()->getMethod() == 'POST') {
                $post_roles = \filter_var($this->getParsedBody()['roles'] ?? [], \FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY);
                if ((empty($post_roles) || !\in_array(1, $post_roles)) && $id == 1) {
                    throw new \Exception('Default user must have a system role', 403);
                }
                if (!$this->configureRoles($id, $post_roles)) {
                    throw new \Exception('No updates');
                }
                return $this->respondJSON([
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                $vars = ['profile' => $record];
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
                $roles_result = $this->query("SELECT id,alias,name,description FROM $roles_table")->fetchAll();
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

                $userRoleModel = new UserRole($this->pdo);
                $current_role = [];
                $select_current_roles =
                    "SELECT rur.role_id FROM {$userRoleModel->getName()} as rur INNER JOIN $roles_table as rr ON rur.role_id=rr.id " .
                    "WHERE rur.user_id=$id";
                $current_roles = $this->query($select_current_roles)->fetchAll();
                foreach ($current_roles as $row) {
                    $current_role[] = $row['role_id'];
                }
                $vars['current_role'] = $current_role;
                
                $this->twigTemplate(__DIR__ . '/user-set-role-modal.html', $vars)->render();
            }
        } catch (\Throwable $err) {            
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $err->getMessage()
                ], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {            
            $context = ['action' => 'set-role', 'id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай хэрэглэгчийн дүрийг тохируулах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $context += ['record' => $record];
                $message = '{id} дугаартай [{record.username}] хэрэглэгчийн дүрийг ';
                if ($this->getRequest()->getMethod() == 'POST') {
                    $level = LogLevel::INFO;
                    $message .= 'амжилттай тохируулав';
                } else {
                    $level = LogLevel::NOTICE;
                    $message .= 'тохируулах үйлдлийг эхлүүллээ';
                    $context += ['current_role' => $current_role];
                }
            }
            $this->indolog('users', $level, $message, $context);
        }
    }
    
    private function configureRoles(int $id, array $roleSets): bool
    {
        $configured = false;
        try {            
            if (!$this->isUserCan('system_user_organization_set')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $logger = new Logger($this->pdo);
            $logger->setTable('users');
            $auth_user = [
                'id' => $this->getUser()->profile['id'],
                'username' => $this->getUser()->profile['username'],
                'first_name' => $this->getUser()->profile['first_name'],
                'last_name' => $this->getUser()->profile['last_name'],
                'phone' => $this->getUser()->profile['phone'],
                'email' => $this->getUser()->profile['email']
            ];
            
            $roles = \array_flip($roleSets);
            $userRoleModel = new UserRole($this->pdo);
            $user_role = $userRoleModel->fetchAllRolesByUser($id);
            foreach ($user_role as $row) {
                if (isset($roles[$row['role_id']])) {
                    unset($roles[$row['role_id']]);
                } elseif ($row['role_id'] == 1 && $id == 1) {
                    // can't delete root user's coder role!
                } elseif ($row['role_id'] == 1 && !$this->isUser('system_coder')) {
                    // only coder can strip another coder role
                } elseif ($userRoleModel->deleteById($row['id'])) {
                    $configured = true;
                    $logger->log(
                        LogLevel::ALERT,
                        '{user_id}-р хэрэглэгчээс {role_id} дугаар бүхий дүрийг хаслаа',
                        ['action' => 'strip-role', 'user_id' => $id, 'role_id' => $row['role_id'], 'auth_user' => $auth_user]
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
                if (!empty($userRoleModel->insert(['user_id' => $id, 'role_id' => $role_id]))) {
                    $configured = true;
                    $logger->log(
                        LogLevel::ALERT,
                        '{user_id}-р хэрэглэгч дээр {role_id} дугаар бүхий дүр нэмлээ',
                        ['action' => 'set-role', 'user_id' => $id, 'role_id' => $role_id, 'auth_user' => $auth_user]
                    );
                }
            }
        } catch (\Throwable) {}
        return $configured;
    }
}
