<?php

namespace Raptor\Authentication;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Raptor\RBAC\RBAC;
use Raptor\User\UsersModel;
use Raptor\Authentication\User;
use Raptor\Organization\OrganizationModel;
use Raptor\Organization\OrganizationUserModel;

\define('INDO_JWT_ALGORITHM', $_ENV['INDO_JWT_ALGORITHM'] ?? 'HS256');
\define('INDO_JWT_SECRET', $_ENV['INDO_JWT_SECRET'] ?? 'codesaur-indoraptor-not-so-secret');

class JWTAuthMiddleware implements MiddlewareInterface
{
    public function generate(array $data): string
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
    
    public function validate(string $jwt): array
    {
        $key = new Key(
            $_ENV['INDO_JWT_SECRET'] ?? 'codesaur-indoraptor-not-so-secret',
            $_ENV['INDO_JWT_ALGORITHM'] ?? 'HS256'
        );
        $result = (array) JWT::decode($jwt, $key);
        $expirationTime = $result['exp'] ?? 0;
        if ($expirationTime < \time()) {
            throw new \Exception('Invalid JWT data or expired!');
        }
        if (!isset($result['user_id']) || !isset($result['organization_id'])) {
            throw new \Exception('Invalid JWT data for codesaur!', 401);
        }
        return $result;
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            if (empty($_SESSION['RAPTOR_JWT'])) {
                throw new \Exception('There is no JWT on the session!', 5000);
            }
            $result = $this->validate($_SESSION['RAPTOR_JWT']);
            
            $pdo = $request->getAttribute('pdo');
            $users = new UsersModel($pdo);
            $profile = $users->getById($result['user_id']);
            if (!isset($profile['id'])) {
                throw new \Exception('User not found', 404);
            }
            if ($profile['status'] != 1) {
                throw new \Exception('Inactive user', 406);
            }
            unset($profile['password']);

            $orgModel = new OrganizationModel($pdo);
            $orgUserModel = new OrganizationUserModel($pdo);
            $stmt = $orgUserModel->prepare(
                'SELECT t2.* ' .
                "FROM {$orgUserModel->getName()} t1 INNER JOIN {$orgModel->getName()} t2 ON t1.organization_id=t2.id " .
                'WHERE t1.user_id=:user AND t1.organization_id=:org AND t1.is_active=1 AND t2.is_active=1 LIMIT 1'
            );
            $stmt->bindParam(':user', $result['user_id'], \PDO::PARAM_INT);
            $stmt->bindParam(':org', $result['organization_id'], \PDO::PARAM_INT);
            if (!$stmt->execute() || $stmt->rowCount() != 1) {
                throw new \Exception('User doesn\'t belong to an organization', 406);
            }
            $organization = $stmt->fetch();
            
            return $handler->handle($request->withAttribute('user', new User(
                $profile, $organization, (new RBAC($pdo, $profile['id']))->jsonSerialize())
            ));
        } catch (\Throwable $err) {
            if ($err->getCode() != 5000 && CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
            
            if (isset($_SESSION['RAPTOR_JWT'])
                && \session_status() == \PHP_SESSION_ACTIVE
            ) {
                unset($_SESSION['RAPTOR_JWT']);
            }
            
            $path = \rawurldecode($request->getUri()->getPath());
            $script_path = \dirname($request->getServerParams()['SCRIPT_NAME']);
            if (($lngth = \strlen($script_path)) > 1) {
                $path = \substr($path, $lngth);
                $path = '/' . \ltrim($path, '/');
            } else {
                $script_path = '';
            }
            
            if ((\explode('/', $path)[2] ?? '' ) != 'login') {
                $loginUri = (string) $request->getUri()->withPath("$script_path/dashboard/login");
                \header("Location: $loginUri", false, 302);
                exit;
            }
            
            return $handler->handle($request);
        }
    }
}
