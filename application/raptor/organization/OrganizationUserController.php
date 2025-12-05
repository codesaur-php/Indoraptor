<?php

namespace Raptor\Organization;

use Psr\Log\LogLevel;

/**
 * Class OrganizationUserController
 *
 * Хэрэглэгчийн харьяалагдах байгууллагуудын жагсаалтыг
 * Dashboard дээр үзүүлэх үүрэгтэй контроллер.
 *
 * Энэ контроллер нь:
 *  - Одоогийн хэрэглэгчийн байгууллагуудыг харуулах
 *  - system_coder хэрэглэгчид бүх байгууллагыг харах эрх олгох
 *  - Зөвхөн эрхтэй хэрэглэгчид хандалтыг зөвшөөрөх
 *  - Dashboard UI рүү өгөгдөл дамжуулж render хийх
 *  - Хэрэглэгчийг харьяалагдах байгууллагуудынхаа жагсаалтаас 
 *    хүссэн байгууллагаа идэвхжүүлж ажиллах боломжоор хангана
 * 
 * Permission levels:
 *  - isUserAuthorized() → Нийтлэг эрх шалгалт
 *  - system_coder → бүх байгууллагыг харах тусгай эрх
 *
 * indolog() лог бүртгэлийн системээр үйлдэл бүрийг журналдана.
 *
 * @package Raptor\Organization
 */
class OrganizationUserController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Хэрэглэгчийн харьяалагдах байгууллагуудын жагсаалтыг dashboard дээр үзүүлэх.
     *
     * Route: GET /dashboard/organization/user/list
     *
     * Хэрэв хэрэглэгч system_coder бол:
     *    → бүх идэвхтэй байгууллагыг харуулна.
     *
     * Харин жирийн хэрэглэгч бол:
     *    → зөвхөн өөрийн харьяалагддаг байгууллагуудыг INNER JOIN ашиглан авч харуулна.
     *
     * @return void
     */
    public function index()
    {
        try {
            // Хэрэглэгч эрхтэй эсэхийг шалгах
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            // Байгууллага болон холболтын хүснэгтийн нэрүүд
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $org_users_table = (new OrganizationUserModel($this->pdo))->getName();
            
            // system_coder бол бүх байгууллагыг харна
            if ($this->isUser('system_coder')) {
                $select_org = "SELECT * FROM $org_table WHERE is_active=1";
            } else {
                // Жирийн хэрэглэгч → зөвхөн өөрийн байгууллагууд
                $select_org =
                    "SELECT t2.* 
                     FROM $org_users_table AS t1 
                     INNER JOIN $org_table AS t2 ON t1.organization_id = t2.id 
                     WHERE t2.is_active = 1 
                       AND t1.user_id = " . $this->getUserId();
            }

            // Dashboard HTML рүү өгөгдөл дамжуулж render хийх
            $dashboard = $this->twigDashboard(
                __DIR__ . '/organization-user.html',
                ['organizations' => $this->query($select_org)->fetchAll()]
            );
            $dashboard->set('title', $this->text('organization'));
            $dashboard->render();
        } catch (\Throwable $err) {
            // Хандалт хориотой үед харагдах UI
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            // Лог бүртгэл
            $context = ['action' => 'organization-user-index'];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = '{error.message}';
                $context += [
                    'error' => [
                        'code' => $err->getCode(),
                        'message' => $err->getMessage()
                    ]
                ];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Хэрэглэгч өөрийн байгууллагуудын жагсаалтыг нээж үзэж байна';
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
}
