<?php

namespace Indoraptor\Account;

use PDO;
use Exception;
use Throwable;

use Fig\Http\Message\StatusCodeInterface;

use codesaur\RBAC\Accounts;

class AccountController extends \Indoraptor\IndoController
{
    public function signup()
    {
        try {
            $payload = $this->getParsedBody();
            if (empty($payload['code'])
                    || empty($payload['email'])
                    || empty($payload['username'])
                    || empty($payload['password'])
                    
            ) {
                throw new Exception('Invalid payload', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            if (filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception('Please provide valid email address', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            $accounts = new Accounts($this->pdo);
            $stmt_e = $this->prepare("SELECT email FROM {$accounts->getName()} WHERE email=:eml");
            $stmt_e->bindParam(':eml', $payload['email'], PDO::PARAM_STR, $accounts->getColumn('email')->getLength());
            $stmt_e->execute();
            if ($stmt_e->rowCount() == 1) {
                throw new Exception('It looks like email address belongs to an existing account', AccountErrorCode::INSERT_DUPLICATE_EMAIL);
            }
            
            $stmt_u = $this->prepare("SELECT username FROM {$accounts->getName()} WHERE username=:usr");
            $stmt_u->bindParam(':usr', $payload['username'], PDO::PARAM_STR, $accounts->getColumn('username')->getLength());
            $stmt_u->execute();
            if ($stmt_u->rowCount() == 1) {
                throw new Exception('It looks like information belongs to an existing account', AccountErrorCode::INSERT_DUPLICATE_USERNAME);
            }
            
            $accounts->setTable('newbie', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $requested_email = $accounts->getRowBy(array('email' => $payload['email']));
            if ($requested_email) {
                throw new Exception('It looks like information belongs to an existing request', AccountErrorCode::INSERT_DUPLICATE_NEWBIE);
            }
            $requested_email_username = $accounts->getRowBy(array('username' => $payload['username']));
            if ($requested_email_username) {
                throw new Exception('It looks like information belongs to an existing request', AccountErrorCode::INSERT_DUPLICATE_NEWBIE);
            }
            $id = $accounts->insert(array(
                'code' => $payload['code'],
                'email' => $payload['email'],
                'username' => $payload['username'],
                'password' => $payload['password'],
                'address' => $payload['organization'] ?? ''
            ));
            if (!$id) {
                throw new Exception('Failed to insert request to an account creation table', AccountErrorCode::INSERT_NEWBIE_FAILURE);
            }
            
            return $this->respond($id);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), $e->getCode());
        }
    }
    
    public function forgot()
    {
        try {
            $payload = $this->getParsedBody();
            if (empty($payload['email'])
                    || filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception('Please provide valid email address', StatusCodeInterface::STATUS_BAD_REQUEST);
            }
            
            $accounts = new Accounts($this->pdo);
            $account = $accounts->getRowBy(array('email' => $payload['email']));
            if (!$account) {
                throw new Exception('No account with that email address exists', AccountErrorCode::ACCOUNT_NOT_FOUND);
            }
            if ($account['is_active'] != 1) {
                throw new Exception('User is not active', AccountErrorCode::ACCOUNT_NOT_ACTIVE);
            }

            $forgotModel = new ForgotModel($this->pdo);
            $forgot = array(
                'use_id'     => uniqid('use'),
                'account'    => $account['id'],
                'email'      => $account['email'],
                'username'   => $account['username'],
                'last_name'  => $account['last_name'],
                'first_name' => $account['first_name']
            );
            if (!empty($payload['code'])) {
                $forgot['code'] = $payload['code'];
            }
            $id = $forgotModel->insert($forgot);
            if (!$id) {
                throw new Exception('Error occurred while inserting forgot record', AccountErrorCode::INSERT_FORGOT_FAILURE);
            }
            $forgot['id'] = $id;
            return $this->respond($forgot);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), $e->getCode());
        }
    }
    
    public function password()
    {
        $payload = $this->getParsedBody();
        if (empty($payload['use_id'])
                || empty($payload['account'])
                || empty($payload['password'])
        ) {
            return $this->badRequest();
        }
        
        $forgot = new ForgotModel($this->pdo);
        $record = $forgot->getRowBy(array('use_id' => $payload['use_id']));
        if (!$record
                || $record['account'] != $payload['account']
        ) {
            return $this->unauthorized();
        }
        
        $model = new Accounts($this->pdo);
        $account = $model->getById($payload['account']);
        if (!$account) {
            return $this->error('Invalid account', AccountErrorCode::ACCOUNT_NOT_FOUND);
        }
        
        $result = $model->updateById($account['id'], array('password' => $payload['password']));
        if (!$result) {
            return $this->error("Can't reset account [{$account['username']}] password", AccountErrorCode::UPDATE_PASSWORD_FAILURE);
        }
        
        $forgot->deleteByID($record['id']);

        unset($account['password']);

        return $this->respond($account);
    }
    
    public function getMenu()
    {
        $auth = $this->validate();        
        if (!isset($auth['account_id'])) {
            return $this->unauthorized();
        }
        
        $model = new MenuModel($this->pdo);
        $sql = "select exists(select 1 from {$model->getName()})";
        $stmt = $model->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_NUM);
        if ($result[0][0] == '0') {
            return $this->notFound('Menu not defined');
        }
        
        return $this->respond($model->getRows(array('ORDER BY' => 'p.position', 'WHERE' => 'p.is_active=1')));
    }
}
