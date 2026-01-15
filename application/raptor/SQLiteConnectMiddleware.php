<?php

namespace Raptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class SQLiteConnectMiddleware
 *
 * Indoraptor Framework-ийн SQLite холболтын middleware.
 *
 * Энэхүү middleware нь:
 *   1) .env файл дахь INDO_DB_* тохиргоонуудыг уншина
 *   2) SQLite database файлтай холбогдож PDO объект үүсгэнэ
 *   3) Database файл байхгүй бол үүсгэнэ (directory байхгүй бол үүсгэнэ)
 *   4) PDO instance-ийг PSR-7 request attributes-д `pdo` нэрээр inject хийнэ
 *   5) Дараагийн middleware / Controller рүү үргэлжлүүлэн дамжуулна
 *
 * MySQL/PostgreSQL-ээс ялгаатай нь:
 *   - SQLite нь файл дээр суурилсан database юм
 *   - Хост, хэрэглэгчийн нэр, нууц үг шаардлагагүй
 *   - Зөвхөн database файлын зам шаардлагатай
 *   - SQLite нь UTF-8-ийг дэмжинэ, charset/collation тохиргоо шаардлагагүй
 *   - SET NAMES гэх мэт SQL командууд шаардлагагүй
 *
 * .env файлд тохируулах:
 *   INDO_DB_NAME=/path/to/database.db  (эсвэл :memory: RAM дээр ажиллуулах)
 *
 * @package Raptor
 */
class SQLiteConnectMiddleware implements MiddlewareInterface
{
    /**
     * Middleware process - SQLite холболт үүсгэх.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     *
     * @throws \PDOException  Холболт буруу бол PDO алдаа шиднэ
     * @throws \Exception     Database файлын directory үүсгэж чадахгүй бол
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // ---------------------------------------------------------------------
        // 1. ENV тохиргоонуудыг унших
        // ---------------------------------------------------------------------
        $database = $_ENV['INDO_DB_NAME'] ?? 'indoraptor.db';

        // SQLite-д зөвхөн database файлын зам шаардлагатай
        // :memory: гэвэл RAM дээр ажиллана (test зориулалтаар)

        // PDO options
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT         => $_ENV['INDO_DB_PERSISTENT'] ?? false
        ];

        // ---------------------------------------------------------------------
        // 2. Database файлын directory байгаа эсэхийг шалгах
        //    (зөвхөн :memory: биш бол)
        // ---------------------------------------------------------------------
        if ($database !== ':memory:') {
            $db_path = \dirname($database);

            // Directory байхгүй бол үүсгэнэ (localhost үед)
            $client_ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';
            if (!empty($db_path) && $db_path !== '.' && !\is_dir($db_path)) {
                if (\in_array($client_ip, ['127.0.0.1', '::1'])) {
                    // Localhost-аас ажиллаж байгаа бол directory үүсгэнэ
                    if (!\mkdir($db_path, 0755, true)) {
                        throw new \Exception("Failed to create database directory: $db_path");
                    }
                }
            }

            // Database файл байхгүй бол үүсгэнэ (PDO холбогдсоны дараа автоматаар үүснэ)
            // SQLite нь файл байхгүй бол автоматаар үүсгэнэ
        }

        // ---------------------------------------------------------------------
        // 3. SQLite database-д холбогдох
        //
        // SQLite DSN хэлбэр:
        //   - sqlite:/absolute/path/to/database.db
        //   - sqlite:./relative/path/to/database.db
        //   - sqlite::memory: (RAM дээр)
        // ---------------------------------------------------------------------
        if ($database === ':memory:') {
            $dsn = 'sqlite::memory:';
        } else {
            // Абсолют зам эсвэл харьцангуй зам байна
            // PDO SQLite driver нь файл байхгүй бол автоматаар үүсгэнэ
            $dsn = "sqlite:$database";
        }

        $pdo = new \PDO($dsn, null, null, $options);

        // ---------------------------------------------------------------------
        // 4. SQLite-д шаардлагатай тохиргоонууд
        //
        // SQLite нь UTF-8-ийг дэмжинэ, charset/collation тохиргоо шаардлагагүй
        // Гэхдээ foreign key constraint-уудыг идэвхжүүлэх шаардлагатай
        // (энэ нь Model классуудын FK constraint үүсгэхэд шаардлагатай)
        // ---------------------------------------------------------------------
        $pdo->exec('PRAGMA foreign_keys = ON');

        // ---------------------------------------------------------------------
        // 5. PDO instance-ийг request attributes рүү inject хийж дамжуулах
        // ---------------------------------------------------------------------
        return $handler->handle(
            $request->withAttribute('pdo', $pdo)
        );
    }
}
