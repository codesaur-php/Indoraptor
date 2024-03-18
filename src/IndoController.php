<?php

namespace Indoraptor;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use codesaur\Http\Application\Controller;
use codesaur\Http\Message\NonBodyResponse;

\define('INDO_JWT_ALGORITHM', $_ENV['INDO_JWT_ALGORITHM'] ?? 'HS256');
\define('INDO_JWT_SECRET', $_ENV['INDO_JWT_SECRET'] ?? 'codesaur-indoraptor-not-so-secret');

class IndoController extends Controller
{
    use \codesaur\DataObject\PDOTrait;
    
    public final function __construct(ServerRequestInterface $request)
    {
        parent::__construct($request);
        
        $this->pdo = $request->getAttribute('pdo');
        
        if ($request->getMethod() == 'INTERNAL'
            && !$request instanceof Internal\InternalRequest
        ) {
            $this->unauthorized();
            exit;
        }
    }
    
    public final function generate(array $data): string
    {
        $issuedAt = \time();
        $lifeSeconds = (int) ($_ENV['INDO_JWT_LIFETIME'] ?? 2592000);
        $expirationTime = $issuedAt;
        $expirationTime += $lifeSeconds;
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'seconds' => $lifeSeconds,
        ] + $data;
        $key = INDO_JWT_SECRET;
        $alg = INDO_JWT_ALGORITHM;
        
        return JWT::encode($payload, $key, $alg);
    }
    
    public final function validate(?string $jwt = null, $secret = null, ?string $algorithm = null): array|string
    {
        try {
            if (empty($jwt)) {
                if (empty($this->getRequest()->getServerParams()['HTTP_AUTHORIZATION'])
                    || \substr($this->getRequest()->getServerParams()['HTTP_AUTHORIZATION'], 0, 7) != 'Bearer '
                ) {
                    throw new \Exception('Undefined JWT!');
                }
                $jwt = \trim(\substr($this->getRequest()->getServerParams()['HTTP_AUTHORIZATION'], 7));
            }
            
            $key = new Key($secret ?? INDO_JWT_SECRET, $algorithm ?? INDO_JWT_ALGORITHM);
            $result = (array) JWT::decode($jwt, $key);
            $expirationTime = $result['exp'] ?? 0;
            if ($expirationTime < \time()) {
                throw new \Exception('Invalid JWT data or expired!');
            }
            if ($result['account_id'] ?? false
                && !\getenv('CODESAUR_ACCOUNT_ID', true)
            ) {
                \putenv("CODESAUR_ACCOUNT_ID={$result['account_id']}");
            }
            return $result;
        } catch (\Throwable $e) {
            if ($this->isDevelopment()) {
                \error_log($e->getMessage());
            }
            
            return $e->getMessage();
        }
    }
    
    public final function isAuthorized(): bool
    {
        return ($this->validate()['account_id'] ?? 0) > 0;
    }

    public function respond($data, int|string $code = 0): ResponseInterface
    {
        $response = new class extends NonBodyResponse
        {
            public function setStatus(int $code)
            {
                $this->status = $code;
            }
        };
        
        echo \json_encode($data)
            ?: '{"error":{"code":500,"message":"Indoraptor: Failed to encode response data!"}}';
        
        if (!empty($code)
            && \is_int($code)
        ) {
            $response->setStatus($code);
        }
        
        return $response;
    }
    
    public function error(string $message, int|string $code): ResponseInterface
    {
        return $this->respond(['error' => ['code' => $code, 'message' => $message]], $code);
    }
    
    public final function badRequest(string $message = 'Bad Request'): ResponseInterface
    {
        return $this->error($message, StatusCodeInterface::STATUS_BAD_REQUEST);
    }
    
    public final function unauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return $this->error($message, StatusCodeInterface::STATUS_UNAUTHORIZED);
    }
    
    public final function forbidden(string $message = 'Forbidden'): ResponseInterface
    {
        return $this->error($message, StatusCodeInterface::STATUS_FORBIDDEN);
    }
    
    public final function notFound(string $message = 'Not found'): ResponseInterface
    {
        return $this->error($message, StatusCodeInterface::STATUS_NOT_FOUND);
    }
    
    protected function grabModel()
    {
        $params = $this->getQueryParams();
        $cls = $params['model'] ?? null;
        if (empty($cls)) {
            $cls = $this->getParsedBody()['model'] ?? null;
        }
        if (empty($cls)) {
            return null;
        }
        
        $class = \str_replace(' ', '', $cls);
        if (!\class_exists($class)) {
            return null;
        }
        
        return new $class($this->pdo);
    }
}
