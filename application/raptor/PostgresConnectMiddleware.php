<?php

namespace Raptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class PostgresConnectMiddleware
 *
 * Indoraptor Framework-ийн PostgreSQL холболтын middleware.
 *
 * Энэхүү middleware нь:
 *   1) .env файл дахь INDO_DB_* тохиргоонуудыг уншина
 *   2) PostgreSQL сервертэй PDO ашиглан холбогдоно
 *   3) UTF8 client encoding тохиргоотой ажиллана
 *   4) PDO instance-ийг PSR-7 request attributes дотор
 *         `pdo` нэрээр inject хийнэ
 *   5) Дараагийн middleware / Controller рүү request-г дамжуулна
 *
 * MySQLConnectMiddleware-ээс ялгаатай нь:
 *   - PostgreSQL дээр автоматаар database үүсгэх (CREATE DATABASE)
 *     ажиллагааг хийхгүй. Энэ нь Postgres-ийн permission policy-д
 *     тохируулсан сонголт юм.
 *
 * @package Raptor
 */
class PostgresConnectMiddleware implements MiddlewareInterface
{
    /**
     * Middleware process - PostgreSQL холболт үүсгэх.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     *
     * @throws \Exception       INDO_DB_PASSWORD тохируулагдаагүй үед
     * @throws \PDOException    Холболтын үед алдаа гарвал
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // ---------------------------------------------------------------------
        // 1. ENV тохиргоонуудыг унших
        // ---------------------------------------------------------------------
        $host     = $_ENV['INDO_DB_HOST']     ?? 'localhost';
        $username = $_ENV['INDO_DB_USERNAME'] ?? 'postgres';
        $database = $_ENV['INDO_DB_NAME']     ?? 'indoraptor';

        // Password заавал байх ёстой
        $password = $_ENV['INDO_DB_PASSWORD']
            ?? throw new \Exception('INDO_DB_PASSWORD is not set. Check your .env file!');

        // PDO Options
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT         => $_ENV['INDO_DB_PERSISTENT'] ?? false,
        ];

        // ---------------------------------------------------------------------
        // 2. PostgreSQL серверт холбогдох
        //
        // client_encoding=UTF8 → Postgres-д charset-г DSN-р зааж өгдөг.
        // ---------------------------------------------------------------------
        $dsn = "pgsql:host=$host;dbname=$database;client_encoding=UTF8";
        $pdo = new \PDO($dsn, $username, $password, $options);

        // ---------------------------------------------------------------------
        // 3. PDO instance-г request attributes дотор хадгална
        // ---------------------------------------------------------------------
        return $handler->handle(
            $request->withAttribute('pdo', $pdo)
        );
    }
}
