<?php

namespace Raptor\Authentication;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

use Raptor\RBAC\RBAC;
use Raptor\User\UsersModel;
use Raptor\Authentication\User;
use Raptor\Organization\OrganizationModel;
use Raptor\Organization\OrganizationUserModel;

class JWTAuthMiddleware implements MiddlewareInterface
{
    // Орчны хувьсагчид тодорхойлоогүй үед ашиглагдах үндсэн алгоритм
    private const DEFAULT_ALGORITHM = 'HS256';

    /**
     * JWT нууц түлхүүрийг орчноос унших.
     * Хэрвээ тохируулагдаагүй бол алдаа үүсгэнэ.
     * Энэ нь production орчинд сул нууц ашиглагдахаас сэргийлнэ.
     *
     * @return string
     */
    private function getSecret(): string
    {
        $secret = $_ENV['INDO_JWT_SECRET'] ?? null;
        if (empty($secret)) {
            throw new \RuntimeException('INDO_JWT_SECRET тохируулагдаагүй байна. Баталгаажуулалт зогсоолоо.');
        }
        return $secret;
    }

    // JWT кодлох алгоритмыг орчноос эсвэл анхны утгаар унших
    private function getAlgorithm(): string
    {
        return $_ENV['INDO_JWT_ALGORITHM'] ?? self::DEFAULT_ALGORITHM;
    }

    // JWT decode хийхэд ашиглагдах түлхүүр объект үүсгэх
    private function getKey(): Key
    {
        return new Key($this->getSecret(), $this->getAlgorithm());
    }

    // JWT токен үүсгэх
    public function generate(array $data): string
    {
        $issuedAt = \time();
        $lifeSeconds = (int) ($_ENV['INDO_JWT_LIFETIME'] ?? 604800); // анхны хугацаа: 7 хоног
        $payload = [
            'iat' => $issuedAt,
            'exp' => $issuedAt + $lifeSeconds,
            'seconds' => $lifeSeconds,
        ] + $data;

        return JWT::encode($payload, $this->getSecret(), $this->getAlgorithm());
    }

    // JWT токенийг шалгах (хугацаа, бүтэц, хэрэглэгчийн мэдээлэл гэх мэт)
    public function validate(string $jwt): array
    {
        // JWT decode үед буруу бол Exception үүснэ
        $decoded = JWT::decode($jwt, $this->getKey());
        $result = (array) $decoded;

        $expirationTime = $result['exp'] ?? 0;
        if ($expirationTime < \time()) {
            throw new \RuntimeException('JWT буруу эсвэл хугацаа дууссан байна!');
        }

        if (!isset($result['user_id']) || !isset($result['organization_id'])) {
            throw new \RuntimeException('JWT мэдээлэл буруу байна!', 401);
        }

        return $result;
    }

    /**
     * Туслах функц: ResponseFactory ашиглан PSR-7 redirect response үүсгэх.
     * Хэрэв Factory байхгүй бол header() ашиглан redirect хийнэ.
     *
     * @param ServerRequestInterface $request
     * @param string $location
     * @param int $status
     * @return ResponseInterface
     */
    private function redirectResponse(ServerRequestInterface $request, string $location, int $status = 302): ResponseInterface
    {
        // Зарим framework нь ResponseFactory-г request attributes-д хадгалдаг
        $factory = $request->getAttribute('responseFactory');
        if ($factory instanceof ResponseFactoryInterface) {
            $response = $factory->createResponse($status)->withHeader('Location', $location);
            return $response;
        }

        // Response объект attribute дотор байгаа эсэхийг шалгах
        $response = $request->getAttribute('response');
        if ($response instanceof ResponseInterface) {
            return $response->withStatus($status)->withHeader('Location', $location);
        }

        // Сүүлийн арга: header() ашиглан redirect хийх
        header("Location: $location", false, $status);
        exit;
    }

    // Middleware процесс — хэрэглэгчийн JWT-г шалгаж, тохирох хэрэглэгчийн объект үүсгэх
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            if (empty($_SESSION['RAPTOR_JWT'])) {
                throw new \RuntimeException('Session дотор JWT байхгүй байна!');
            }

            $result = $this->validate($_SESSION['RAPTOR_JWT']);

            $pdo = $request->getAttribute('pdo');
            $users = new UsersModel($pdo);
            $profile = $users->getRowWhere([
                'id' => $result['user_id'],
                'is_active' => 1
            ]);

            if (!isset($profile['id'])) {
                throw new \RuntimeException('Хэрэглэгч олдсонгүй', 404);
            }

            unset($profile['password']);

            // Байгууллагын холболтыг шалгах
            $orgModel = new OrganizationModel($pdo);
            $orgUserModel = new OrganizationUserModel($pdo);
            $stmt = $orgUserModel->prepare(
                'SELECT t2.* ' .
                "FROM {$orgUserModel->getName()} t1 INNER JOIN {$orgModel->getName()} t2 ON t1.organization_id=t2.id " .
                'WHERE t1.user_id=:user AND t1.organization_id=:org AND t2.is_active=1 LIMIT 1'
            );
            $stmt->bindParam(':user', $result['user_id'], \PDO::PARAM_INT);
            $stmt->bindParam(':org', $result['organization_id'], \PDO::PARAM_INT);
            if (!$stmt->execute() || $stmt->rowCount() != 1) {
                throw new \RuntimeException('Хэрэглэгч тухайн байгууллагад харьяалагдахгүй байна', 406);
            }
            $organization = $stmt->fetch();

            // Хэрэглэгчийн RBAC эрхийг агуулсан объект үүсгэх
            $userObject = new User($profile, $organization, (new RBAC($pdo, $profile['id']))->jsonSerialize());

            // Хэрэглэгчийн мэдээллийг request attribute-д хадгалан дараагийн middleware рүү дамжуулах
            return $handler->handle($request->withAttribute('user', $userObject));
        } catch (\Throwable $err) {
            // JWT байгаа бол дахин ашиглагдахаас сэргийлж устгана
            if (isset($_SESSION['RAPTOR_JWT'])) {
                if (\session_status() == \PHP_SESSION_ACTIVE) {
                    unset($_SESSION['RAPTOR_JWT']);
                }
                if (defined('CODESAUR_DEVELOPMENT') && CODESAUR_DEVELOPMENT) {
                    \error_log($err->getMessage());
                }
            }

            // URI замыг боловсруулах (redirect хийхэд ашиглана)
            $path = \rawurldecode($request->getUri()->getPath());
            $script_path = \dirname($request->getServerParams()['SCRIPT_NAME']);
            if (($lngth = \strlen($script_path)) > 1) {
                $path = \substr($path, $lngth);
                $path = '/' . \ltrim($path, '/');
            } else {
                $script_path = '';
            }

            // Хэрэв login хуудас дээр биш бол redirect хийнэ
            if ((\explode('/', $path)[2] ?? '' ) != 'login') {
                $loginUri = (string) $request->getUri()->withPath("$script_path/dashboard/login");
                return $this->redirectResponse($request, $loginUri, 302);
            }

            // Хэрэв аль хэдийн login хуудас дээр байвал процессийг үргэлжлүүлнэ
            return $handler->handle($request);
        }
    }
}
