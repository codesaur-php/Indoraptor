<?php

namespace Indoraptor\Account;

use PDO;
use Exception;

use Fig\Http\Message\StatusCodeInterface;

use codesaur\RBAC\RBACUser;
use codesaur\RBAC\Accounts;

class AuthController extends \Indoraptor\IndoController
{
    public function jwt()
    {
        try {
            $payload = $this->getParsedBody();
            if (empty($payload['jwt'])) {
                throw new Exception('Please provide information', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            $validation = $this->validate($payload['jwt']);
            if (!isset($validation['account_id'])) {
                throw new Exception('Invalid JWT - Authentication failed', StatusCodeInterface::STATUS_UNAUTHORIZED);
            }

            $accounts = new Accounts($this->pdo);
            $account = $accounts->getById($validation['account_id']);
            if (!isset($account['id'])) {
                throw new Exception('Account not found', AccountErrorCode::ACCOUNT_NOT_FOUND);
            }
            unset($account['password']);

            $organizations = array();            
            $org_model = new OrganizationModel($this->pdo);
            $org_user_model = new OrganizationUserModel($this->pdo);            
            $stmt = $this->prepare(
                    'SELECT t2.id, t2.name, t2.logo, t2.alias, t2.external'
                    . " FROM {$org_user_model->getName()} t1 JOIN {$org_model->getName()} t2 ON t1.organization_id=t2.id"
                    . ' WHERE t1.account_id=:id AND t1.is_active=1 AND t1.status=1 AND t2.is_active=1 ORDER BY t2.name');
            $stmt->bindParam(':id', $account['id'], PDO::PARAM_INT);
            if ($stmt->execute()) {
                $index = 0;
                $current = $validation['organization_id'] ?? 1;
                if ($stmt->rowCount() > 0) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $index++;
                        $row['id'] = (int)$row['id'];
                        $organizations[$row['id'] == $current ? 0 : $index] = $row;
                    }
                }
            }
            
            if (empty($organizations)) {
                throw new Exception('User doesn\'t belong to an organization', StatusCodeInterface::STATUS_FORBIDDEN);
            } elseif (!isset($organizations[0])) {
                $organizations[0] = $organizations[1];
                unset($organizations[1]);
            }

            return $this->respond(array(
                'account' => $account,
                'organizations' => $organizations,
                'rbac' => new RBACUser($this->pdo, $account['id'], $organizations[0]['alias'])
            ));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode());
        }
    }

    public function entry()
    {
        try {
            $payload = $this->getParsedBody();
            if (empty($payload['username']) || empty($payload['password'])) {
                throw new Exception('Invalid payload', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            $accounts = new Accounts($this->pdo);
            $stmt = $accounts->prepare("SELECT * FROM {$accounts->getName()} WHERE username=:usr OR email=:eml LIMIT 1");
            $stmt->bindParam(':eml', $payload['username'], PDO::PARAM_STR, $accounts->getColumn('email')->getLength());
            $stmt->bindParam(':usr', $payload['username'], PDO::PARAM_STR, $accounts->getColumn('username')->getLength());
            $stmt->execute();
            if ($stmt->rowCount() != 1) {
                throw new Exception('Invalid username or password', AccountErrorCode::INCORRECT_CREDENTIALS);
            }
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!password_verify($payload['password'], $account['password'])) {
                throw new Exception('Invalid username or password', AccountErrorCode::INCORRECT_CREDENTIALS);
            }
            
            foreach ($accounts->getColumns() as $column) {
                if (isset($account[$column->getName()])) {
                    if ($column->isInt()) {
                        $account[$column->getName()] = (int)$account[$column->getName()];
                    } elseif ($column->getType() == 'decimal') {
                        $account[$column->getName()] = (float)$account[$column->getName()];
                    }
                }
            }
            if ($account['status'] == 0) {
                throw new Exception('User is not active', AccountErrorCode::ACCOUNT_NOT_ACTIVE);
            }
            unset($account['password']);
            
            $login_info = array('account_id' => $account['id']);
            $last = $this->getLastLoginOrg($account['id']);
            if ($last !== null) {
                $login_info['organization_id'] = $last;
            }            
            $account['jwt'] = $this->generate($login_info);
            
            return $this->respond(array('account' => $account));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode());
        }
    }
    
    public function organization()
    {
        try {
            $current_login = $this->validate();
            if (!is_array($current_login)) {
                throw new Exception('Not allowed', StatusCodeInterface::STATUS_UNAUTHORIZED);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['account_id'])
                    || !is_int($payload['account_id'])
                    || !isset($payload['organization_id'])
                    || !is_int($payload['organization_id'])
            ) {
                throw new Exception('Invalid request', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            $accounts = new Accounts($this->pdo);
            $account = $accounts->getById($current_login['account_id']);
            if (!isset($account['id'])
                    || $account['id'] != $payload['account_id']
            ) {
                throw new Exception('Invalid account', AccountErrorCode::ACCOUNT_NOT_FOUND);
            }
            
            $org_model = new OrganizationModel($this->pdo);
            $organization = $org_model->getById($payload['organization_id']);
            if (!isset($organization['id'])) {
                throw new Exception('Invalid organization', AccountErrorCode::ORGANIZATION_NOT_FOUND);
            }
            
            $org_user_model = new OrganizationUserModel($this->pdo);
            $user = $org_user_model->retrieve($organization['id'], $account['id']);
            if (empty($user['id'])) {
                $rbacUser = new RBACUser($this->pdo, $account['id']);
                if (!$rbacUser->hasRole('system_coder')) {
                    throw new Exception('Account does not belong to an organization', StatusCodeInterface::STATUS_FORBIDDEN);
                }
            }

            $account_org_jwt = array(
                'account_id' => $account['id'],
                'organization_id' => $organization['id']
            );            
            return $this->respond(array('jwt' => $this->generate($account_org_jwt)));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode());
        }
    }
    
    function getLastLoginOrg(int $account_id)
    {
        if (!$this->hasTable('dashboard_log')) {
            return null;
        }
        
        $level = 'critical';
        $login_like = json_encode(array('login-to-organization' => '%'));
        $stmt = $this->prepare('SELECT context FROM dashboard_log WHERE created_by=:id AND context LIKE :co AND level=:le ORDER BY id Desc LIMIT 1');
        $stmt->bindParam(':id', $account_id, PDO::PARAM_INT);
        $stmt->bindParam(':co', $login_like);
        $stmt->bindParam(':le', $level);
        $stmt->execute();
        if ($stmt->rowCount() != 1) {
            return null;
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $context = json_decode($result['context'], true);
        
        $org_user_model = new OrganizationUserModel($this->pdo);        
        $org_user_query = "SELECT id FROM {$org_user_model->getName()}"
                . ' WHERE organization_id=:org AND account_id=:acc AND status=1 AND is_active=1'
                . ' ORDER By id Desc LIMIT 1';
        $org_user_stmt = $this->prepare($org_user_query);
        $org_user_stmt->bindParam(':org', $context['login-to-organization']);
        $org_user_stmt->bindParam(':acc', $account_id, PDO::PARAM_INT);
        $org_user_stmt->execute();

        if ($org_user_stmt->rowCount() != 1) {
            return null;
        }

        return (int)$context['login-to-organization'];
    }
}
