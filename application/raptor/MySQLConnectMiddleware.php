<?php

namespace Raptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class MySQLConnectMiddleware
 *
 * Indoraptor Framework-ийн MySQL холболтын middleware.
 *
 * Энэхүү middleware нь:
 *   1) .env файл дахь INDO_DB_* тохиргоонуудыг уншина
 *   2) MySQL серверт холбогдож PDO объект үүсгэнэ
 *   3) Localhost орчинд (127.0.0.1 / ::1) ажиллаж байвал
 *        → database-г байхгүй үед автоматаар үүсгэнэ (CREATE DATABASE IF NOT EXISTS)
 *   4) Зөв charset / collation тохиргоог идэвхжүүлнэ
 *   5) PDO instance-ийг PSR-7 request attributes-д `pdo` нэрээр inject хийнэ
 *   6) Дараагийн middleware / Controller рүү үргэлжлүүлэн дамжуулна
 *
 * Энэ нь бүх Controller-уудын $this->pdo ашиглах боломжийг олгодог
 * Indoraptor-ийн үндсэн дата холболтын давхарга юм.
 *
 * @package Raptor
 */
class MySQLConnectMiddleware implements MiddlewareInterface
{
    /**
     * Middleware process - MySQL холболтыг үүсгэх.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     *
     * @throws \PDOException  Холболт буруу бол PDO алдаа шиднэ
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // ---------------------------------------------------------------------
        // 1. ENV тохиргоонуудыг унших (өгөгдөөгүй бол default утга авна)
        // ---------------------------------------------------------------------
        $host      = $_ENV['INDO_DB_HOST']      ?? 'localhost';
        $username  = $_ENV['INDO_DB_USERNAME']  ?? 'root';
        $password  = $_ENV['INDO_DB_PASSWORD']  ?? '';
        $charset   = $_ENV['INDO_DB_CHARSET']   ?? 'utf8mb4';
        $collation = $_ENV['INDO_DB_COLLATION'] ?? 'utf8mb4_unicode_ci';

        // PDO options
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT         => $_ENV['INDO_DB_PERSISTENT'] ?? false
        ];

        // ---------------------------------------------------------------------
        // 2. MySQL серверт холбогдох
        // ---------------------------------------------------------------------
        $dsn = "mysql:host=$host;charset=$charset";
        $pdo = new \PDO($dsn, $username, $password, $options);

        // ---------------------------------------------------------------------
        // 3. Database автоматаар үүсгэх (зөвхөн localhost үед)
        //    Энэ нь хөгжүүлэлтийн ажлыг ихээхэн хөнгөвчилдөг.
        // ---------------------------------------------------------------------
        $database = $_ENV['INDO_DB_NAME'] ?? 'indoraptor';

        $client_ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        if (\in_array($client_ip, ['127.0.0.1', '::1'])) {
            // Localhost-аас ажиллаж байгаа бол хэрвээ бааз байхгүй бол шинээр үүсгээд, колляц тохируулна
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` COLLATE $collation");
        }

        // ---------------------------------------------------------------------
        // 4. Ашиглах database-г сонгох + charset/collation тохируулах
        // ---------------------------------------------------------------------
        $pdo->exec("USE `$database`");
        $pdo->exec("SET NAMES '$charset' COLLATE '$collation'");

        // ---------------------------------------------------------------------
        // 5. PDO instance-ийг request attributes рүү inject хийж дамжуулах
        // ---------------------------------------------------------------------
        return $handler->handle(
            $request->withAttribute('pdo', $pdo)
        );
    }
}
