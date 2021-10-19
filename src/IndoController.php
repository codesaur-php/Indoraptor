<?php

namespace Indoraptor;

use Exception;

use Firebase\JWT\JWT;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ServerRequestInterface;

use codesaur\Http\Application\Controller;
use codesaur\Http\Message\NonBodyResponse;

define('INDO_JWT_LIFETIME', getenv('INDO_JWT_LIFETIME', true) ?: 2592000);
define('INDO_JWT_ALGORITHM', getenv('INDO_JWT_ALGORITHM', true) ?: 'HS256');
define('INDO_JWT_SECRET', getenv('INDO_JWT_SECRET', true) ?: 'codesaur-indoraptor-not-so-secret');

class IndoController extends Controller
{
    use \codesaur\DataObject\PDOTrait;
    
    final function __construct(ServerRequestInterface $request)
    {
        parent::__construct($request);
        
        $this->pdo = $request->getAttribute('pdo');
    }
    
    final public function generate(array $data)
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + INDO_JWT_LIFETIME;
        $payload = array(
            'iat' => $issuedAt,
            'exp' => $expirationTime
        ) + $data;
        $key = INDO_JWT_SECRET;
        $alg = INDO_JWT_ALGORITHM;
        
        return JWT::encode($payload, $key, $alg);
    }
    
    final public function validate($jwt = null, $secret = null, $algs = null)
    {
        try {
            if (empty($jwt)) {
                if (!empty($this->getRequest()->getServerParams()['HTTP_JWT'])) {
                    $jwt = $this->getRequest()->getServerParams()['HTTP_JWT'];
                } else {
                    $header_jwt = $this->getRequest()->getHeader('INDO_JWT');
                    if (!empty($header_jwt)) {
                        $jwt = current($header_jwt);
                    } else {
                        throw new Exception('Undefined JWT!');
                    }
                }
            }
            
            $result = (array) JWT::decode($jwt,
                    $secret ?? INDO_JWT_SECRET,
                    $algs ?? array(INDO_JWT_ALGORITHM));
            if ($result['account_id'] ?? false &&
                !getenv('CODESAUR_ACCOUNT_ID', true)) {
                putenv("CODESAUR_ACCOUNT_ID={$result['account_id']}");
            }
            return $result;
        } catch (Exception $ex) {
            if ($this->isDevelopment()) {
                error_log($ex->getMessage());
            }
            
            return $ex->getMessage();
        }
    }
    
    final public function isAuthorized(): bool
    {
        return is_array($this->validate());
    }

    final public function respond($data, $status = null)
    {
        $response = new class extends NonBodyResponse
        {
            public function setStatus($code)
            {
                if (!empty($code)) {
                    $this->status = (int)$code;
                }
            }
        };
        
        echo json_encode($data);
        
        try {
            $response->setStatus($status);
        } catch (Exception $ex) {
            unset($ex);
        }
        
        return $response;
    }
    
    final public function error($message, $status)
    {
        return $this->respond(array('error' => array('code' => $status, 'message' => $message)), $status);
    }
    
    final public function badRequest(string $message = 'Bad Request')
    {
        return $this->error($message, StatusCodeInterface::STATUS_BAD_REQUEST);
    }
    
    final public function unauthorized(string $message = 'Unauthorized')
    {
        return $this->error($message, StatusCodeInterface::STATUS_UNAUTHORIZED);
    }
    
    final public function forbidden(string $message = 'Forbidden')
    {
        return $this->error($message, StatusCodeInterface::STATUS_FORBIDDEN);
    }
    
    final public function notFound(string $message = 'Not found')
    {
        return $this->error($message, StatusCodeInterface::STATUS_NOT_FOUND);
    }
    
    public function grabModel()
    {
        $cls = $this->getQueryParam('model')
                ?? $this->getPostParam('model', FILTER_SANITIZE_STRING);
        if (!empty($cls)) {
            $class = str_replace(' ', '', $cls);
            if (class_exists($class)) {
                $model = new $class($this->pdo);
                if (method_exists($model, 'setTable')) {
                    $table = $this->getQueryParam('table')
                            ?? $this->getPostParam('table', FILTER_SANITIZE_STRING);
                    if (!empty($table)) {
                        $model->setTable((string)$table,
                                getenv('INDO_DB_COLLATION', true) ?: 'utf8_unicode_ci');
                    }
                    return $model;
                }
            }
        }
        return $this->badRequest();
    }
}
