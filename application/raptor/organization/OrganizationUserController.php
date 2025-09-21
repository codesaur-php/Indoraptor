<?php

namespace Raptor\Organization;

use Psr\Log\LogLevel;

class OrganizationUserController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $org_users_table = (new OrganizationUserModel($this->pdo))->getName();
            if ($this->isUser('system_coder')) {
                $select_org = "SELECT * FROM $org_table WHERE is_active=1";
            } else {
                $select_org =
                    "SELECT t2.* FROM $org_users_table as t1 INNER JOIN $org_table as t2 ON t1.organization_id=t2.id " .
                    'WHERE t2.is_active=1 AND t1.user_id=' . $this->getUserId();
            }
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/organization-user.html',
                ['organizations' => $this->query($select_org)->fetchAll()]
            );
            $dashboard->set('title', $this->text('organization'));
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'organization-user-index'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{error.message}';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Хэрэглэгч өөрийн байгууллагуудын жагсаалтыг нээж үзэж байна';
            }
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
}
