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

/**
 * Class JWTAuthMiddleware
 *
 * Indoraptor Dashboard болон Web хэсэгт нэвтэрсэн хэрэглэгчийн
 * баталгаажуулалтыг (Authentication) гүйцэтгэх middleware.
 *
 * Энэ middleware нь:
 *   - Session дотор хадгалсан JWT токеныг шалгана
 *   - JWT-г decode хийж, хугацаа нь дууссан эсэхийг үзнэ
 *   - Хэрэглэгч ба байгууллагын мэдээллийг баталгаажуулна
 *   - RBAC эрхүүдийг ачаалж 'User' объект үүсгэнэ
 *   - Дараагийн middleware / Controller рүү дамжуулна
 *
 * JWT байхгүй, буруу, хугацаа дууссан, эсвэл хэрэглэгч/байгууллага
 * тохирохгүй үед хэрэглэгчийг /dashboard/login хуудас руу redirect хийнэ.
 *
 * @package Raptor\Authentication
 */
class JWTAuthMiddleware implements MiddlewareInterface
{
    /**
     * Орчны хувьсагчаар тодорхойлоогүй үед ашиглагдах үндсэн JWT алгоритм.
     */
    private const DEFAULT_ALGORITHM = 'HS256';

    /**
     * JWT нууц түлхүүрийг ENV-аас уншина.
     * Хэрвээ тохируулагдаагүй бол Authentication-ийг шууд зогсооно.
     *
     * Энэ нь production орчин дахь аюулгүй байдлыг хамгаалах
     * хамгийн чухал safeguard юм.
     *
     * @return string
     * @throws RuntimeException
     */
    private function getSecret(): string
    {
        $secret = $_ENV['INDO_JWT_SECRET'] ?? null;
        if (empty($secret)) {
            throw new \RuntimeException(
                'INDO_JWT_SECRET тохируулагдаагүй байна. JWT баталгаажуулалт үргэлжлэх боломжгүй.'
            );
        }
        return $secret;
    }

    /**
     * JWT кодлох/тайлахад ашиглагдах алгоритмыг ENV-аас унших.
     * Хэрвээ тохируулагдаагүй бол анхны HS256 алгоритм хэрэглэнэ.
     *
     * @return string
     */
    private function getAlgorithm(): string
    {
        return $_ENV['INDO_JWT_ALGORITHM'] ?? self::DEFAULT_ALGORITHM;
    }

    /**
     * JWT decode хийхэд шаардлагатай Key объект үүсгэдэг.
     *
     * @return Key
     */
    private function getKey(): Key
    {
        return new Key($this->getSecret(), $this->getAlgorithm());
    }

    /**
     * JWT токен үүсгэгч функц.
     * Нэвтэрсэн хэрэглэгчийн мэдээллийг payload дотор хадгална.
     *
     * Payload:
     *   - iat  : issued at
     *   - exp  : хугацаа дуусах огноо
     *   - seconds : токений амьдрах хугацаа
     *   - хэрэглэгч ба байгууллагын мэдээлэл
     *
     * @param array $data  Payload дотор орох мэдээлэл
     * @return string       Кодолсон JWT токен
     */
    public function generate(array $data): string
    {
        $issuedAt = \time();
        $lifeSeconds = (int) ($_ENV['INDO_JWT_LIFETIME'] ?? 604800); // 604800 гэдэг бол 7 хоног

        $payload = [
            'iat' => $issuedAt,
            'exp' => $issuedAt + $lifeSeconds,
            'seconds' => $lifeSeconds,
        ] + $data;

        return JWT::encode($payload, $this->getSecret(), $this->getAlgorithm());
    }

    /**
     * JWT токеныг decode хийж, хугацаа дууссан эсэх,
     * payload бүтэц бүрэн эсэхийг шалгана.
     *
     * @param string $jwt
     * @return array
     *
     * @throws RuntimeException JWT буруу, хугацаа дууссан,
     *                          эсвэл шаардлагатай талбар дутуу үед
     */
    public function validate(string $jwt): array
    {
        // Decode үед буруу бол Exception босно
        $decoded = JWT::decode($jwt, $this->getKey());
        $result = (array) $decoded;

        if (($result['exp'] ?? 0) < \time()) {
            throw new \RuntimeException('JWT хугацаа дууссан байна.');
        }

        if (!isset($result['user_id']) || !isset($result['organization_id'])) {
            throw new \RuntimeException('JWT мэдээлэл дутуу байна.', 401);
        }

        return $result;
    }

    /**
     * Redirect хийх универсал PSR-7 арга.
     * ResponseFactory → Response → header()  гэсэн 3 шаттай fallback.
     *
     * @param ServerRequestInterface $request
     * @param string $location
     * @param int $status
     * @return ResponseInterface
     */
    private function redirectResponse(
        ServerRequestInterface $request,
        string $location,
        int $status = 302
    ): ResponseInterface {
        // 1) ResponseFactory байгаа эсэх
        $factory = $request->getAttribute('responseFactory');
        if ($factory instanceof ResponseFactoryInterface) {
            return $factory->createResponse($status)->withHeader('Location', $location);
        }

        // 2) Request дотор Response өгөгдсөн эсэх
        $response = $request->getAttribute('response');
        if ($response instanceof ResponseInterface) {
            return $response->withStatus($status)->withHeader('Location', $location);
        }

        // 3) Сүүлийн арга — Browser redirect
        header("Location: $location", false, $status);
        exit;
    }

    /**
     * Middleware процесс.
     *
     * 1) Session-д хадгалсан JWT-г уншина
     * 2) JWT-г шалгана (decode + exp)
     * 3) Хэрэглэгчийн profile-г DB-с татаж шалгана
     * 4) Хэрэглэгч тухайн байгууллагад харьяалагдах эсэхийг хянана
     * 5) RBAC эрхүүдийг ачаалж 'User' объект үүсгэнэ
     * 6) Request attributes дотор user-г хадгалаад дараагийн middleware рүү дамжина
     *
     * Хэрвээ JWT асуудалтай бол → login руу redirect хийнэ.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // -------------------------------------------------------------
            // 1. JWT Session-д байхгүй бол нэвтрээгүй гэж үзнэ
            // -------------------------------------------------------------
            if (empty($_SESSION['RAPTOR_JWT'])) {
                throw new \RuntimeException('Session дотор JWT байхгүй байна.');
            }

            // -------------------------------------------------------------
            // 2. JWT decode + validate
            // -------------------------------------------------------------
            $result = $this->validate($_SESSION['RAPTOR_JWT']);

            // -------------------------------------------------------------
            // 3. Хэрэглэгчийн profile баталгаажуулах
            // -------------------------------------------------------------
            $pdo = $request->getAttribute('pdo');

            $users = new UsersModel($pdo);
            $profile = $users->getRowWhere([
                'id'        => $result['user_id'],
                'is_active' => 1,
            ]);

            if (!isset($profile['id'])) {
                throw new \RuntimeException('Хэрэглэгч олдсонгүй.', 404);
            }

            unset($profile['password']);

            // -------------------------------------------------------------
            // 4. Хэрэглэгч тухайн байгууллагад харьяалагдах эсэхийг шалгах
            // -------------------------------------------------------------
            $orgModel     = new OrganizationModel($pdo);
            $orgUserModel = new OrganizationUserModel($pdo);

            $stmt = $orgUserModel->prepare(
                'SELECT t2.* ' .
                "FROM {$orgUserModel->getName()} t1 " .
                "INNER JOIN {$orgModel->getName()} t2 ON t1.organization_id=t2.id " .
                'WHERE t1.user_id=:user AND t1.organization_id=:org AND t2.is_active=1 LIMIT 1'
            );

            $stmt->bindParam(':user', $result['user_id'], \PDO::PARAM_INT);
            $stmt->bindParam(':org',  $result['organization_id'], \PDO::PARAM_INT);

            if (!$stmt->execute() || $stmt->rowCount() !== 1) {
                throw new \RuntimeException('Хэрэглэгч тухайн байгууллагад харьяалагдахгүй байна.', 406);
            }

            $organization = $stmt->fetch();

            // -------------------------------------------------------------
            // 5. RBAC эрхүүдийг ачаалан User объект үүсгэх
            // -------------------------------------------------------------
            $permissions = (new RBAC($pdo, $profile['id']))->jsonSerialize();

            $userObject = new User($profile, $organization, $permissions);

            // -------------------------------------------------------------
            // 6. Request-д user attribute нэмээд үргэлжлүүлэх
            // -------------------------------------------------------------
            return $handler->handle(
                $request->withAttribute('user', $userObject)
            );
        }

        // ==============================================================
        // JWT алдаа гарсан тохиолдол
        // ==============================================================
        catch (\Throwable $err) {
            // JWT-г дахин ашиглуулахгүйн тулд устгана
            if (isset($_SESSION['RAPTOR_JWT'])
                && \session_status() === \PHP_SESSION_ACTIVE
            ) {
                unset($_SESSION['RAPTOR_JWT']);
            }

            // Хөгжүүлэлтийн горимд байх үед лог хадгалах
            if (defined('CODESAUR_DEVELOPMENT')
                && CODESAUR_DEVELOPMENT
            ) {
                \error_log($err->getMessage());
            }

            // ---------------------------------------------------------
            // Redirect замыг зөв тооцоолох
            // ---------------------------------------------------------
            $path = \rawurldecode($request->getUri()->getPath());
            $scriptPath = \dirname($request->getServerParams()['SCRIPT_NAME']);
            if (($len = \strlen($scriptPath)) > 1) {
                $path = '/' . \ltrim(\substr($path, $len), '/');
            } else {
                $scriptPath = '';
            }

            // Login хуудас дээр биш бол redirect хийнэ
            if ((\explode('/', $path)[2] ?? '') !== 'login') {
                $loginUri = (string) $request->getUri()->withPath("$scriptPath/dashboard/login");
                return $this->redirectResponse($request, $loginUri, 302);
            }

            // Login дээр байвал процессийг үргэлжлүүлнэ
            return $handler->handle($request);
        }
    }
}
