<?php

namespace Indoraptor;

use Exception;
use Throwable;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ServerRequestInterface;

use codesaur\DataObject\TableTrait;
use codesaur\Http\Application\Controller;
use codesaur\Http\Message\NonBodyResponse;

define('INDO_JWT_ALGORITHM', $_ENV['INDO_JWT_ALGORITHM'] ?? 'HS256');
define('INDO_JWT_SECRET', $_ENV['INDO_JWT_SECRET'] ?? 'codesaur-indoraptor-not-so-secret');

class IndoController extends Controller
{
    use \codesaur\DataObject\PDOTrait;
    
    final function __construct(ServerRequestInterface $request)
    {
        parent::__construct($request);
        
        $this->pdo = $request->getAttribute('pdo');
        
        if ($request->getMethod() == 'INTERNAL'
            && !$request instanceof InternalRequest
        ) {
             $this->unauthorized();
             exit;
        }
    }
    
    final public function generate(array $data)
    {
        $issuedAt = time();
        $lifeSeconds = (int)($_ENV['INDO_JWT_LIFETIME'] ?? 2592000);
        $expirationTime = $issuedAt;
        $expirationTime += $lifeSeconds;
        $payload = array(
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'seconds' => $lifeSeconds,
        ) + $data;
        $key = INDO_JWT_SECRET;
        $alg = INDO_JWT_ALGORITHM;
        
        return JWT::encode($payload, $key, $alg);
    }
    
    final public function validate($jwt = null, $secret = null, $algs = null)
    {
        try {
            if (empty($jwt)) {
                if (empty($this->getRequest()->getServerParams()['HTTP_AUTHORIZATION'])
                    || substr($this->getRequest()->getServerParams()['HTTP_AUTHORIZATION'], 0, 7) !== 'Bearer '
                ) {
                    throw new Exception('Undefined JWT!');
                }
                $jwt = trim(substr($this->getRequest()->getServerParams()['HTTP_AUTHORIZATION'], 7));
            }
            
            $result = (array) JWT::decode($jwt,
                    new Key($secret ?? INDO_JWT_SECRET, $algs ?? INDO_JWT_ALGORITHM));
            $expirationTime = $result['exp'] ?? 0;
            if ($expirationTime < time()) {
                throw new Exception('Invalid JWT data or expired!');
            }
            if ($result['account_id'] ?? false &&
                !getenv('CODESAUR_ACCOUNT_ID', true)
            ) {
                putenv("CODESAUR_ACCOUNT_ID={$result['account_id']}");
            }
            return $result;
        } catch (Throwable $e) {
            if ($this->isDevelopment()) {
                error_log($e->getMessage());
            }
            
            return $e->getMessage();
        }
    }
    
    final public function isAuthorized(): bool
    {
        return is_array($this->validate());
    }

    public function respond($data, $status = null)
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
        } catch (Throwable $e) {
            unset($e);
        }
        
        return $response;
    }
    
    public function error($message, $status)
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
        $params = $this->getQueryParams();
        $cls = $params['model'] ?? null;
        if (empty($cls)) {
            $cls = $this->getParsedBody()['model'] ?? null;
        }
        if (empty($cls)) {
            return null;
        }
        
        $class = str_replace(' ', '', $cls);
        if (!class_exists($class)) {
            return null;
        }
        
        return new $class($this->pdo);
    }
}
