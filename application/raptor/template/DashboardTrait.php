<?php

namespace Raptor\Template;

use codesaur\Template\TwigTemplate;

use Raptor\User\UsersModel;

trait DashboardTrait
{
    public function twigDashboard(string $template, array $vars = []): TwigTemplate
    {
        $dashboard = $this->twigTemplate(\dirname(__FILE__) . '/dashboard.html');
        $dashboard->set('sidemenu', $this->getUserMenu());
        $dashboard->set('content', $this->twigTemplate($template, $vars));        
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $dashboard->set($key, $value);
        }
        return $dashboard;
    }
    
    public function dashboardProhibited(?string $alert = null, int|string $code = 0): TwigTemplate
    {
        $this->headerResponseCode($code);
        
        return $this->twigDashboard(
            \dirname(__FILE__) . '/alert-no-permission.html',
            ['alert' => $alert ?? $this->text('system-no-permission')]);
    }
    
    public function modalProhibited(?string $alert = null, int|string $code = 0): TwigTemplate
    {
        $this->headerResponseCode($code);
        
        return new TwigTemplate(
            \dirname(__FILE__) . '/modal-no-permission.html',
            ['alert' => $alert ?? $this->text('system-no-permission'), 'close' => $this->text('close')]);
    }
    
    protected function retrieveUsersDetail(?int ...$ids)
    {
        $users = [];
        try {
            $had_condition = !empty($ids);
            $table = (new UsersModel($this->pdo))->getName();
            $select_users = "SELECT id,username,first_name,last_name,email FROM $table";
            if ($had_condition) {
                $ids = \array_filter($ids, function ($v) { return $v !== null; });
                if (empty($ids)) {
                    throw new \Exception(__FUNCTION__ . ': invalid arguments!');
                }
                \array_walk($ids, function(&$v) { $v = "id=$v"; });
                $select_users .= ' WHERE ' . \implode(' OR ', $ids);
            }
            $pdo_stmt = $this->prepare($select_users);
            if ($pdo_stmt->execute()) {
                while ($row = $pdo_stmt->fetch()) {
                    $users[$row['id']] = "{$row['username']} » {$row['first_name']} {$row['last_name']} ({$row['email']})";
                }
            }
        } catch (\Throwable $e) {
            $this->errorLog($e);
        }
        return $users;
    }
    
    public function getUserMenu(): array
    {
        $sidemenu = [];
        try {
            $model = new MenuModel($this->pdo);
            $alias = $this->getUser()->organization['alias'];
            $code = $this->getLanguageCode();
            $rows = $model->getRows(['ORDER BY' => 'p.position', 'WHERE' => 'p.is_active=1 AND p.is_visible=1']);
            foreach ($rows as $row) {
                $title = $row['localized']['title'][$code] ?? null;
                if (!isset($title)) {
                    continue;
                }
                if (!empty($row['alias']) && $alias != $row['alias']) {
                    continue;
                }
                if (!empty($row['permission'])
                    && !$this->isUserCan($row['permission'])
                ) {
                    continue;
                }

                if ($row['parent_id'] == 0) {
                    if (isset($sidemenu[$row['id']])) {
                        $sidemenu[$row['id']]['title'] = $title;
                    } else {
                        $sidemenu[$row['id']] = ['title' => $title, 'submenu' => []];
                    }
                } else {
                    unset($row['localized']);
                    $row['title'] = $title;
                    if (!isset($sidemenu[$row['parent_id']])) {
                        $sidemenu[$row['parent_id']] = ['title' => '', 'submenu' => [$row]];
                    } else {
                        $sidemenu[$row['parent_id']]['submenu'][] = $row;
                    }
                }
            }

            foreach ($sidemenu as $key => $rows) {
                if (empty($rows['submenu'])) {
                    unset($sidemenu[$key]);
                 }
            }
        } catch (\Throwable $e) {
            $this->errorLog($e);
        }
        return $sidemenu;
    }
}
