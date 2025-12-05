<?php

namespace Raptor\Organization;

use Psr\Log\LogLevel;
use Raptor\Content\FileController;

/**
 * Class OrganizationController
 *
 * Байгууллагын модультай холбоотой бүх HTTP хүсэлтийг хүлээн авч
 * боловсруулдаг үндсэн контроллер.
 *
 * Энэ контроллер нь:
 *  - Байгууллагын жагсаалт үзэх (index, list)
 *  - Байгууллага шинээр үүсгэх (insert)
 *  - Байгууллагын дэлгэрэнгүй харах (view)
 *  - Байгууллага засах (update)
 *  - Байгууллагыг идэвхгүй болгох (deactivate)
 *
 * FileController-ийг өргөтгөсөн тул файл байршуулалт (logo upload),
 * хавтас үүсгэх, зөвшөөрөгдөх MIME төрөл шалгах зэрэг боломжийг агуулдаг.
 *
 * DashboardTrait - Indoraptor Dashboard UI-тай уялдуулан template
 * render хийх боломж олгоно.
 *
 * @package Raptor\Organization
 */
class OrganizationController extends FileController
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Байгууллагын dashboard жагсаалтыг харуулах.
     *
     * Route: GET /dashboard/organizations
     *
     * Permission: system_organization_index
     *
     * Хэрэв хэрэглэгч эрхгүй бол 401 алдаатай хуудас render хийнэ.
     * Амжилттай бол байгууллагын жагсаалт үзэх HTML-ийг render хийнэ.
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUserCan('system_organization_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $dashboard = $this->twigDashboard(__DIR__ . '/organization-index.html');
        $dashboard->set('title', $this->text('organizations'));
        $dashboard->render();

        $this->indolog(
            'organizations',
            LogLevel::NOTICE,
            'Байгууллагуудын жагсаалтыг үзэж байна',
            ['action' => 'index']
        );
    }

    /**
     * Байгууллагын жагсаалтын өгөгдлийг JSON хэлбэрээр буцаах.
     *
     * Route: GET /dashboard/organizations/list
     *
     * Permission: system_organization_index
     *
     * @return void JSON хэвлэнэ
     */
    public function list()
    {
        try {
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $table = (new OrganizationModel($this->pdo))->getName();
            $this->respondJSON([
                'status' => 'success',
                'list' => $this->query(
                    "SELECT id,name,alias,logo,logo_size 
                     FROM $table 
                     WHERE is_active=1 
                     ORDER BY id"
                )->fetchAll()
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }

    /**
     * Байгууллага шинээр үүсгэх.
     *
     * Routes:
     *  - GET  /dashboard/organizations/insert (modal form)
     *  - POST /dashboard/organizations/insert (submit)
     *
     * Permission: system_organization_insert
     *
     * Лого байршуулах боломжтой. Амжилттай бол JSON success хэвлэнэ.
     *
     * @return void
     * @throws Throwable
     */
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_organization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new OrganizationModel($this->pdo);

            // POST → Шинэ байгууллага үүсгэх
            if ($this->getRequest()->getMethod() == 'POST') {
                $payload = $this->getParsedBody();
                if (empty($payload['alias']) || empty($payload['name'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }

                // Мөр үүсгэх
                $record = $model->insert($payload + ['created_by' => $this->getUserId()]);
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $id = $record['id'];

                // Лого байршуулалт
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

                // Амжилттай insert JSON хэвлэе
                $this->respondJSON([
                    'status'  => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                // GET → Modal form render
                $this->twigTemplate(
                    __DIR__ . '/organization-insert-modal.html',
                    ['parents' => $model->fetchAllPotentialParents()]
                )->render();
            }
        } catch (\Throwable $err) {
            // POST submit үед JSON алдаа
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                // GET үед modal error
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // үйлдлийн лог
            $context = ['action' => 'create'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Байгууллага үүсгэх үед алдаа гарлаа';
                $context += [
                    'error' => [
                        'code' => $err->getCode(),
                        'message' => $err->getMessage()
                    ]
                ];
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

    /**
     * Байгууллагын дэлгэрэнгүйг харах.
     *
     * Route: GET /dashboard/organizations/view/{id}
     *
     * Permission: system_organization_index
     *
     * created_by / updated_by талбаруудыг хэрэглэгчийн дэлгэрэнгүй мэдээлэлтэй нь хамт буцаана.
     *
     * @param int $id Байгууллагын дугаар
     * @return void
     */
    public function view(int $id)
    {
        try {
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new OrganizationModel($this->pdo);
            $record = $model->getRowWhere([
                'id'        => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            // created_by, updated_by → хэрэглэгчийн дэлгэрэнгүй
            $record['rbac_users'] = $this->retrieveUsersDetail(
                $record['created_by'],
                $record['updated_by']
            );

            // Эцэг байгууллага шалгах
            if (!empty($record['parent_id'])) {
                $parent = $model->getRowWhere([
                    'id'        => $record['parent_id'],
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
                $message = '{id} дугаартай байгууллагын мэдээллийг нээх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.name}] байгууллагын мэдээллийг үзэж байна';
                $context += ['record' => $record];
            }
            $this->indolog('organizations', $level, $message, $context);
        }
    }

    /**
     * Байгууллагын мэдээллийг шинэчлэх (UPDATE).
     *
     * Routes:
     *  - GET /dashboard/organizations/update/{id}
     *  - PUT /dashboard/organizations/update/{id}
     *
     * Permission: system_organization_update
     *
     * Лого солих, устгах, шинэчилсэн талбаруудыг ялган update хийх,
     * өөрчлөгдөөгүй талбар байвал update хийхгүй.
     *
     * @param int $id Байгууллагын дугаар
     * @return void
     */
    public function update(int $id)
    {
        try {
            if (!$this->isUserCan('system_organization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new OrganizationModel($this->pdo);
            $record = $model->getRowWhere([
                'id'        => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            if ($this->getRequest()->getMethod() == 'PUT') {
                // PUT → Update submission

                $payload = $this->getParsedBody();
                if (empty($payload['alias']) || empty($payload['name'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }

                // alias → зөвшөөрөгдөх тэмдэгт үлдээх
                $payload['alias'] = \preg_replace('/[^A-Za-z0-9_-]/', '', $payload['alias']);

                // Лого устгах
                if ($payload['logo_removed'] == 1) {
                    // logo файл устгах
                    if (\file_exists($record['logo_file'])) {
                        \unlink($record['logo_file']);
                        $record['logo_file'] = '';
                    }
                    $payload['logo']      = '';
                    $payload['logo_file'] = '';
                    $payload['logo_size'] = 0;
                }
                unset($payload['logo_removed']);

                // Лого шинээр байрлуулах
                $this->setFolder("/{$model->getName()}/$id");
                $this->allowImageOnly();
                $logo = $this->moveUploaded('logo');
                if ($logo) {
                    // Хуучин лого байвал устгана
                    if (!empty($record['logo_file']) && \file_exists($record['logo_file'])) {
                        \unlink($record['logo_file']);
                    }
                    
                    // Шинэ лого мэдээлэл
                    $payload['logo']      = $logo['path'];
                    $payload['logo_file'] = $logo['file'];
                    $payload['logo_size'] = $logo['size'];
                }

                // Өөрчлөгдсөн талбаруудыг тодорхойлох
                $updates = [];
                foreach ($payload as $field => $value) {
                    if ($record[$field] != $value) {
                        $updates[] = $field;
                    }
                }
                if (empty($updates)) {
                    // Өөрчлөгдсөн талбарууд байхгүй үед зогсооно
                    throw new \InvalidArgumentException('No update!');
                }

                // Update metadata
                $payload['updated_at'] = \date('Y-m-d H:i:s');
                $payload['updated_by'] = $this->getUserId();
                // Update хийе
                $updated = $model->updateById($id, $payload);
                if (empty($updated)) {
                    // Амжилтгүй тул алдаа шиднээ
                    throw new \Exception($this->text('no-record-selected'));
                }

                // Амжилттай JSON
                $this->respondJSON([
                    'status'  => 'success',
                    'type'    => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                // GET → Update form modal
                $this->twigTemplate(
                    __DIR__ . '/organization-update-modal.html',
                    [
                        'record'  => $record,
                        'parents' => $model->fetchAllPotentialParents()
                    ]
                )->render();
            }
        } catch (\Throwable $err) {
            // Алдаа гарсан үед → PUT=JSON / GET=Modal хэлбэрээр хариулна
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // Лог бүртгэх
            $context = ['action' => 'update', 'id' => $id];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай байгууллагыг шинэчлэх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.name}] байгууллагын мэдээллийг амжилттай шинэчиллээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.name}] байгууллагын мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record];
            }
            $this->indolog('organizations', $level, $message, $context);
        }
    }

    /**
     * Байгууллагыг идэвхгүй болгох (SOFT DELETE).
     *
     * Route: DELETE /dashboard/organizations/deactivate
     *
     * Permission: system_organization_delete
     *
     * Анхан байгууллага (id=1) болон одоо идэвхтэй байгууллагыг устгахыг хориглоно.
     *
     * @return void JSON хэвлэнэ
     */
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
            $id = (int) $payload['id'];

            // id=1 → системийн байгууллага тул устгахгүй
            if ($id == 1) {
                throw new \Exception('Cannot remove first organization!', 403);
            }

            // Одоо ашиглаж буй байгууллагыг устгахыг хориглох
            if ($this->getUser()->organization['id'] == $id) {
                throw new \Exception('Cannot remove currently active organization!', 403);
            }

            // -------------------------------------------------------------
            // Soft delete - байгууллагын бичлэгийг is_active = 0 болгож идэвхгүй болгоно.
            //
            // Анхаарах зүйл:
            //   • Энэ горимд байгууллагын logo файлыг серверээс устгахгүй.
            //   • Учир нь тухайн байгууллагыг ирээдүйд дахин идэвхжүүлэх (reactivate)
            //     боломж нээлттэй тул зураг болон мэдээллүүдийг хадгалж үлдээх шаардлагатай.
            //   • Хэрэв бүрэн устгах (hard delete) үйлдэл бол logo файлыг бас устгах хэрэгтэй.
            //
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
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'Байгууллагыг идэвхгүй болгох явцад алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message =
                    '{server_request.body.id} дугаартай [{server_request.body.name}] байгууллагыг ' .
                    '[{server_request.body.reason}] шалтгаанаар идэвхгүй болголоо';
            }
            $this->indolog('organizations', $level, $message, $context);
        }
    }
}
