<?php

namespace Raptor\Authentication;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fig\Http\Message\StatusCodeInterface;

use Raptor\User\UsersModel;
use Raptor\Authentication\User;
use Raptor\Organization\OrganizationModel;
use Raptor\Organization\OrganizationUserModel;

\define('INDO_JWT_ALGORITHM', $_ENV['INDO_JWT_ALGORITHM'] ?? 'HS256');
\define('INDO_JWT_SECRET', $_ENV['INDO_JWT_SECRET'] ?? 'codesaur-indoraptor-not-so-secret');

class JWTAuthMiddleware implements MiddlewareInterface
{
    public function generateJWT(array $data): string
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
    
    public function validate(string $jwt, $secret = null, ?string $algorithm = null): array
    {
        if (empty($jwt)) {
            throw new \Exception('Please provide JWT information!', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
        
        $key = new Key($secret ?? INDO_JWT_SECRET, $algorithm ?? INDO_JWT_ALGORITHM);
        $result = (array) JWT::decode($jwt, $key);
        $expirationTime = $result['exp'] ?? 0;
        if ($expirationTime < \time()) {
            throw new \Exception('Invalid JWT data or expired!');
        }
        if (!isset($result['user_id'])) {
            throw new \Exception('Invalid JWT data for codesaur!', StatusCodeInterface::STATUS_UNAUTHORIZED);
        }
        return $result;
    }

    private function retrieveJWTUser(ServerRequestInterface $request, string $jwt): User
    {
        $pdo = $request->getAttribute('pdo');
        $validation = $this->validate($jwt);
        $users = new UsersModel($pdo);
        $user = $users->getById($validation['user_id']);
        if (!isset($user['id'])) {
            throw new \Exception('User not found', StatusCodeInterface::STATUS_NOT_FOUND);
        }
        if ($user['status'] != 1) {
            throw new \Exception('Inactive user', StatusCodeInterface::STATUS_NOT_ACCEPTABLE);
        }
        unset($user['password']);

        $organizations = [];
        $org_table = (new OrganizationModel($pdo))->getName();
        $org_user_table = (new OrganizationUserModel($pdo))->getName();
        $stmt = $pdo->prepare(
            'SELECT t2.* ' .
            "FROM $org_user_table t1 INNER JOIN $org_table t2 ON t1.organization_id=t2.id " .
            'WHERE t1.user_id=:id AND t1.is_active=1 AND t2.is_active=1 ORDER BY t2.name'
        );
        $stmt->bindParam(':id', $user['id'], \PDO::PARAM_INT);
        if ($stmt->execute()) {
            $index = 0;
            $current = $validation['organization_id'] ?? 1;
            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch()) {
                    $index++;
                    $row['id'] = (int) $row['id'];
                    $organizations[$row['id'] == $current ? 0 : $index] = $row;
                }
            }
        }

        if (empty($organizations)) {
            throw new \Exception('User doesn\'t belong to an organization', StatusCodeInterface::STATUS_NOT_ACCEPTABLE);
        } elseif (!isset($organizations[0])) {
            $organizations[0] = $organizations[1];
            unset($organizations[1]);
        }
        

        return new User(
            $user,
            $organizations,
            (new \Raptor\RBAC\RBAC($pdo, $user['id']))->jsonSerialize(),
            $jwt
        );
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            if (empty($_SESSION['RAPTOR_JWT'])) {
                throw new \Exception('There is no JWT on the session!');
            }
            $user = $this->retrieveJWTUser($request, $_SESSION['RAPTOR_JWT']);
        } catch (\Throwable $e) {
            if ($e->getCode() >= 5000 && CODESAUR_DEVELOPMENT) {
                \error_log($e->getMessage());
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
        
        return $handler->handle($request->withAttribute('user', $user));
    }
}
